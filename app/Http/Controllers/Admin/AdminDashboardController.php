<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\DirectMessageReport;
use App\Models\PlatformAuditLog;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $activeStatuses = [
            ReportStatus::Open->value,
            ReportStatus::Reviewing->value,
        ];

        return Inertia::render('admin/index', [
            'metrics' => [
                'membersTotal' => User::query()->count(),
                'membersVerified' => User::query()->whereNotNull('email_verified_at')->count(),
                'membersSuspended' => User::query()->whereNotNull('suspended_at')->count(),
                'administratorsTotal' => User::query()->where('platform_role', 'administrator')->count(),
                'spacesTotal' => Space::query()->count(),
                'postsTotal' => Post::query()->count(),
                'commentsTotal' => Comment::query()->count(),
                'communityReportsActive' => PostReport::query()->whereIn('status', $activeStatuses)->count()
                    + CommentReport::query()->whereIn('status', $activeStatuses)->count(),
                'messageReportsActive' => DirectMessageReport::query()
                    ->whereIn('status', $activeStatuses)
                    ->count(),
            ],
            'auditLogs' => PlatformAuditLog::query()
                ->with(['actor:id,name', 'subject:id,name,handle'])
                ->latest('id')
                ->limit(6)
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
