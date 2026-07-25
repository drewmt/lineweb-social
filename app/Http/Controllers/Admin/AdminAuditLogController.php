<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditLogController extends Controller
{
    /** @var array<string, list<PlatformAuditAction>> */
    private const FILTERS = [
        'accounts' => [
            PlatformAuditAction::MemberSuspended,
            PlatformAuditAction::MemberReinstated,
            PlatformAuditAction::AppealSubmitted,
            PlatformAuditAction::AppealReviewStarted,
            PlatformAuditAction::AppealApproved,
            PlatformAuditAction::AppealDenied,
        ],
        'safety' => [
            PlatformAuditAction::DirectMessageReportReviewStarted,
            PlatformAuditAction::DirectMessageReportResolved,
            PlatformAuditAction::DirectMessageReportDismissed,
            PlatformAuditAction::DirectMessageReportReopened,
        ],
        'access' => [
            PlatformAuditAction::AdministratorGranted,
            PlatformAuditAction::AdministratorRevoked,
        ],
    ];

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['all', ...array_keys(self::FILTERS)])],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));
        $category = (string) ($validated['category'] ?? 'all');

        $logs = PlatformAuditLog::query()
            ->with(['actor:id,name', 'subject:id,name,handle'])
            ->when($category !== 'all', fn (Builder $logs) => $logs
                ->whereIn('action', array_map(
                    fn (PlatformAuditAction $action): string => $action->value,
                    self::FILTERS[$category],
                )))
            ->when($query !== '', fn (Builder $logs) => $logs
                ->where(function (Builder $search) use ($query): void {
                    $search
                        ->where('reason', 'like', "%{$query}%")
                        ->orWhereHas('actor', fn (Builder $actors) => $actors
                            ->where('name', 'like', "%{$query}%"))
                        ->orWhereHas('subject', fn (Builder $subjects) => $subjects
                            ->where(function (Builder $member) use ($query): void {
                                $member
                                    ->where('name', 'like', "%{$query}%")
                                    ->orWhere('handle', 'like', "%{$query}%");
                            }));
                }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PlatformAuditLog $log): array => [
                'id' => $log->getKey(),
                'action' => $log->action->value,
                'reason' => $log->reason,
                'actorName' => $log->actor->name ?? 'Console',
                'subjectName' => $log->subject->name ?? 'Deleted member',
                'subjectHandle' => $log->subject?->handle,
                'createdAt' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/audit', [
            'query' => $query,
            'category' => $category,
            'counts' => [
                'all' => PlatformAuditLog::query()->count(),
                ...collect(self::FILTERS)
                    ->map(fn (array $actions): int => PlatformAuditLog::query()
                        ->whereIn('action', array_map(
                            fn (PlatformAuditAction $action): string => $action->value,
                            $actions,
                        ))
                        ->count())
                    ->all(),
            ],
            'logs' => $logs,
        ]);
    }
}
