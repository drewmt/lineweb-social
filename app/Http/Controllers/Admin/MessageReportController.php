<?php

namespace App\Http\Controllers\Admin;

use App\Community\ManageDirectMessageReports;
use App\Enums\DirectMessageReportAction;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateDirectMessageReportRequest;
use App\Models\DirectMessageReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MessageReportController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'resolved', 'dismissed', 'all'])],
        ]);
        $filter = (string) ($validated['status'] ?? 'active');
        $activeStatuses = [
            ReportStatus::Open->value,
            ReportStatus::Reviewing->value,
        ];
        $reports = DirectMessageReport::query()
            ->when($filter === 'active', fn (Builder $reports) => $reports
                ->whereIn('status', $activeStatuses))
            ->when($filter === 'resolved', fn (Builder $reports) => $reports
                ->where('status', ReportStatus::Resolved))
            ->when($filter === 'dismissed', fn (Builder $reports) => $reports
                ->where('status', ReportStatus::Dismissed))
            ->with([
                'reporter:id,name,handle',
                'reportedUser:id,name,handle',
                'reviewer:id,name',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DirectMessageReport $report): array => [
                'id' => $report->getKey(),
                'actionUrl' => route('admin.message-reports.update', $report),
                'reason' => $report->reason->value,
                'reasonLabel' => $report->reason->label(),
                'details' => $report->details,
                'status' => $report->status->value,
                'statusLabel' => $this->statusLabel($report->status),
                'reporter' => $report->reporter !== null
                    ? [
                        'name' => $report->reporter->name,
                        'handle' => $report->reporter->handle,
                    ]
                    : null,
                'reportedMember' => $report->reportedUser !== null
                    ? [
                        'name' => $report->reportedUser->name,
                        'handle' => $report->reportedUser->handle,
                    ]
                    : null,
                'reviewerName' => $report->reviewer?->name,
                'reviewerNote' => $report->reviewer_note,
                'createdAt' => $report->created_at->toIso8601String(),
                'reviewedAt' => $report->reviewed_at?->toIso8601String(),
                'message' => [
                    'body' => $report->message_body_snapshot,
                    'sentAt' => $report->message_sent_at?->toIso8601String(),
                    'sourceAvailable' => $report->direct_message_id !== null,
                ],
            ]);

        $counts = DirectMessageReport::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('admin/message-reports', [
            'filter' => $filter,
            'counts' => [
                'active' => (int) $counts->get(ReportStatus::Open->value, 0)
                    + (int) $counts->get(ReportStatus::Reviewing->value, 0),
                'resolved' => (int) $counts->get(ReportStatus::Resolved->value, 0),
                'dismissed' => (int) $counts->get(ReportStatus::Dismissed->value, 0),
                'all' => (int) $counts->sum(),
            ],
            'reports' => $reports,
        ]);
    }

    public function update(
        ModerateDirectMessageReportRequest $request,
        DirectMessageReport $directMessageReport,
        ManageDirectMessageReports $reports,
    ): RedirectResponse {
        /** @var User $administrator */
        $administrator = $request->user();
        $action = DirectMessageReportAction::from(
            $request->string('action')->toString(),
        );

        $reports->moderate(
            $directMessageReport,
            $administrator,
            $action,
            $request->string('note')->toString(),
        );

        return redirect()->route('admin.message-reports.index')->with(
            'status',
            match ($action) {
                DirectMessageReportAction::Review => 'Message report moved into review.',
                DirectMessageReportAction::Resolve => 'Message report resolved.',
                DirectMessageReportAction::Dismiss => 'Message report dismissed.',
                DirectMessageReportAction::Reopen => 'Message report reopened.',
            },
        );
    }

    private function statusLabel(ReportStatus $status): string
    {
        return match ($status) {
            ReportStatus::Open => 'Open',
            ReportStatus::Reviewing => 'In review',
            ReportStatus::Resolved => 'Resolved',
            ReportStatus::Dismissed => 'Dismissed',
        };
    }
}
