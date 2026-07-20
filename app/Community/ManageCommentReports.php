<?php

namespace App\Community;

use App\Community\Moderation\ReportWorkflow;
use App\Enums\ReportAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\SpaceAuditAction;
use App\Events\CommentReported;
use App\Events\CommentReportModerated;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ManageCommentReports
{
    public function __construct(private readonly ReportWorkflow $workflow) {}

    public function submit(Comment $comment, User $reporter, ReportReason $reason, ?string $details): CommentReport
    {
        if (Gate::forUser($reporter)->denies('report', $comment)) {
            throw new AuthorizationException('You cannot report this comment.');
        }

        $report = DB::transaction(function () use ($comment, $reporter, $reason, $details): CommentReport {
            $lockedComment = Comment::query()
                ->with(['post.space', 'post.author', 'author'])
                ->whereKey($comment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (Gate::forUser($reporter)->denies('report', $lockedComment)) {
                throw new AuthorizationException('You cannot report this comment.');
            }

            $report = CommentReport::query()->firstOrCreate(
                [
                    'comment_id' => $lockedComment->getKey(),
                    'reporter_id' => $reporter->getKey(),
                ],
                [
                    'space_id' => $lockedComment->post->space_id,
                    'reason' => $reason,
                    'details' => $details,
                    'status' => ReportStatus::Open,
                ],
            );

            if (! $report->wasRecentlyCreated) {
                throw ValidationException::withMessages([
                    'reason' => 'You have already reported this comment.',
                ]);
            }

            return $report;
        });

        CommentReported::dispatch($report);

        return $report;
    }

    public function moderate(
        Space $space,
        CommentReport $report,
        User $moderator,
        ReportAction $action,
        ?string $note,
    ): CommentReport {
        if (Gate::forUser($moderator)->denies('moderate', $space)
            || $report->space_id !== $space->getKey()) {
            throw new AuthorizationException('You cannot moderate this report.');
        }

        $report = DB::transaction(function () use ($space, $report, $moderator, $action, $note): CommentReport {
            $lockedSpace = Space::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();
            $lockedReport = CommentReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $lockedComment = Comment::query()
                ->with('post:id,space_id')
                ->whereKey($lockedReport->comment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReport->space_id !== $lockedSpace->getKey()
                || $lockedComment->post->space_id !== $lockedSpace->getKey()
                || Gate::forUser($moderator)->denies('moderate', $lockedSpace)) {
                throw new AuthorizationException('You cannot moderate this report.');
            }

            $this->applyAction($lockedReport, $lockedComment, $moderator, $action, $note);
            $this->recordAudit($lockedSpace, $lockedReport, $moderator, $action, $note);

            return $lockedReport->refresh();
        });

        CommentReportModerated::dispatch($report, $action);

        return $report;
    }

    private function applyAction(
        CommentReport $report,
        Comment $comment,
        User $moderator,
        ReportAction $action,
        ?string $note,
    ): void {
        $nextStatus = $this->workflow->nextStatus($report->status, $action);

        if ($action === ReportAction::Hide) {
            $comment->forceFill([
                'hidden_at' => $comment->hidden_at ?? now(),
                'hidden_by' => $comment->hidden_by ?? $moderator->getKey(),
                'moderation_note' => $note,
            ])->save();
        }

        $hasOtherResolvedReports = $action === ReportAction::Reopen
            && CommentReport::query()
                ->where('comment_id', $comment->getKey())
                ->whereKeyNot($report->getKey())
                ->where('status', ReportStatus::Resolved)
                ->exists();

        if ($action === ReportAction::Reopen
            && $comment->hidden_at !== null
            && ! $hasOtherResolvedReports) {
            $comment->forceFill([
                'hidden_at' => null,
                'hidden_by' => null,
                'moderation_note' => null,
            ])->save();
        }

        $report->forceFill([
            'status' => $nextStatus,
            'reviewed_by' => $moderator->getKey(),
            'reviewed_at' => now(),
            'moderator_note' => $note,
        ])->save();
    }

    private function recordAudit(
        Space $space,
        CommentReport $report,
        User $moderator,
        ReportAction $action,
        ?string $note,
    ): void {
        SpaceAuditLog::query()->create([
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'action' => match ($action) {
                ReportAction::Review => SpaceAuditAction::CommentReportReviewStarted,
                ReportAction::Hide => SpaceAuditAction::CommentReportResolved,
                ReportAction::Dismiss => SpaceAuditAction::CommentReportDismissed,
                ReportAction::Reopen => SpaceAuditAction::CommentReportReopened,
            },
            'reason' => $note,
            'context' => [
                'report_id' => $report->getKey(),
                'comment_id' => $report->comment_id,
                'report_reason' => $report->reason->value,
            ],
        ]);
    }
}
