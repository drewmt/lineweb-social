<?php

namespace App\Community;

use App\Enums\DirectMessageReportAction;
use App\Enums\PlatformAuditAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Events\DirectMessageReported;
use App\Events\DirectMessageReportModerated;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\DirectMessageReport;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageDirectMessageReports
{
    public function submit(
        Conversation $conversation,
        DirectMessage $message,
        User $reporter,
        ReportReason $reason,
        ?string $details,
    ): DirectMessageReport {
        $this->authorizeSubmission($conversation, $message, $reporter);

        $report = DB::transaction(function () use (
            $conversation,
            $message,
            $reporter,
            $reason,
            $details,
        ): DirectMessageReport {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedMessage = DirectMessage::query()
                ->whereKey($message)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeSubmission($lockedConversation, $lockedMessage, $reporter);

            $report = DirectMessageReport::query()->firstOrCreate(
                [
                    'direct_message_id' => $lockedMessage->getKey(),
                    'reporter_id' => $reporter->getKey(),
                ],
                [
                    'reported_user_id' => $lockedMessage->sender_id,
                    'reason' => $reason,
                    'details' => $details,
                    'message_body_snapshot' => $lockedMessage->body,
                    'message_sent_at' => $lockedMessage->created_at,
                    'status' => ReportStatus::Open,
                ],
            );

            if (! $report->wasRecentlyCreated) {
                throw ValidationException::withMessages([
                    'reason' => 'You have already reported this message.',
                ]);
            }

            return $report;
        }, 3);

        DirectMessageReported::dispatch($report);

        return $report;
    }

    public function moderate(
        DirectMessageReport $report,
        User $administrator,
        DirectMessageReportAction $action,
        string $note,
    ): DirectMessageReport {
        $report = DB::transaction(function () use (
            $report,
            $administrator,
            $action,
            $note,
        ): DirectMessageReport {
            $lockedAdministrator = User::query()
                ->whereKey($administrator)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedReport = DirectMessageReport::query()
                ->whereKey($report)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedAdministrator->isAdministrator()
                || $lockedAdministrator->isSuspended()) {
                throw new AuthorizationException;
            }

            $nextStatus = $this->nextStatus($lockedReport->status, $action);
            $lockedReport->forceFill([
                'status' => $nextStatus,
                'reviewed_by' => $lockedAdministrator->getKey(),
                'reviewed_at' => now(),
                'reviewer_note' => $note,
            ])->save();

            PlatformAuditLog::query()->create([
                'actor_id' => $lockedAdministrator->getKey(),
                'subject_user_id' => $lockedReport->reported_user_id,
                'action' => match ($action) {
                    DirectMessageReportAction::Review => PlatformAuditAction::DirectMessageReportReviewStarted,
                    DirectMessageReportAction::Resolve => PlatformAuditAction::DirectMessageReportResolved,
                    DirectMessageReportAction::Dismiss => PlatformAuditAction::DirectMessageReportDismissed,
                    DirectMessageReportAction::Reopen => PlatformAuditAction::DirectMessageReportReopened,
                },
                'reason' => $note,
                'context' => [
                    'report_id' => $lockedReport->getKey(),
                    'direct_message_id' => $lockedReport->direct_message_id,
                    'report_reason' => $lockedReport->reason->value,
                ],
            ]);

            return $lockedReport->refresh();
        }, 3);

        DirectMessageReportModerated::dispatch($report, $action);

        return $report;
    }

    private function authorizeSubmission(
        Conversation $conversation,
        DirectMessage $message,
        User $reporter,
    ): void {
        if ($message->conversation_id !== $conversation->getKey()
            || Gate::forUser($reporter)->denies('report', $message)) {
            throw new AuthorizationException('You cannot report this message.');
        }
    }

    private function nextStatus(
        ReportStatus $current,
        DirectMessageReportAction $action,
    ): ReportStatus {
        if ($action === DirectMessageReportAction::Review
            && $current !== ReportStatus::Open) {
            $this->invalidTransition('Only an open report can be moved into review.');
        }

        if (in_array($action, [
            DirectMessageReportAction::Resolve,
            DirectMessageReportAction::Dismiss,
        ], true) && ! $current->isActive()) {
            $this->invalidTransition('This report already has a decision. Reopen it before changing the outcome.');
        }

        if ($action === DirectMessageReportAction::Reopen
            && $current->isActive()) {
            $this->invalidTransition('This report is already active.');
        }

        return match ($action) {
            DirectMessageReportAction::Review,
            DirectMessageReportAction::Reopen => ReportStatus::Reviewing,
            DirectMessageReportAction::Resolve => ReportStatus::Resolved,
            DirectMessageReportAction::Dismiss => ReportStatus::Dismissed,
        };
    }

    private function invalidTransition(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
