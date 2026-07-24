<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\PlatformAuditLog;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));

        $members = User::query()
            ->when($query !== '', fn (Builder $members) => $members
                ->where(function (Builder $search) use ($query): void {
                    $search
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('handle', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                }))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'handle' => $member->handle,
                'email' => $member->email,
                'platformRole' => $member->platform_role->value,
                'suspendedAt' => $member->suspended_at?->toIso8601String(),
                'joinedAt' => $member->created_at?->toIso8601String(),
                'isSelf' => $member->is($request->user()),
                'canSuspend' => ! $member->isAdministrator() && ! $member->is($request->user()),
            ]);

        $activeStatuses = [
            ReportStatus::Open->value,
            ReportStatus::Reviewing->value,
        ];

        return Inertia::render('admin/index', [
            'query' => $query,
            'metrics' => [
                'membersTotal' => User::query()->count(),
                'membersVerified' => User::query()->whereNotNull('email_verified_at')->count(),
                'membersSuspended' => User::query()->whereNotNull('suspended_at')->count(),
                'spacesTotal' => Space::query()->count(),
                'postsTotal' => Post::query()->count(),
                'commentsTotal' => Comment::query()->count(),
                'reportsActive' => PostReport::query()->whereIn('status', $activeStatuses)->count()
                    + CommentReport::query()->whereIn('status', $activeStatuses)->count(),
            ],
            'members' => $members,
            'auditLogs' => PlatformAuditLog::query()
                ->with(['actor:id,name', 'subject:id,name,handle'])
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (PlatformAuditLog $log): array => [
                    'id' => $log->getKey(),
                    'action' => $log->action->value,
                    'reason' => $log->reason,
                    'actorName' => $log->actor->name ?? 'Console',
                    'subjectName' => $log->subject->name ?? 'Deleted member',
                    'subjectHandle' => $log->subject?->handle,
                    'createdAt' => $log->created_at->toIso8601String(),
                ])
                ->values(),
        ]);
    }
}
