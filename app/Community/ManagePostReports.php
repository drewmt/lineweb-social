<?php

namespace App\Community;

use App\Community\Moderation\ReportWorkflow;
use App\Enums\ReportAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\SpaceAuditAction;
use App\Events\PostReported;
use App\Events\PostReportModerated;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ManagePostReports
{
    public function __construct(private readonly ReportWorkflow $workflow) {}

    public function submit(Post $post, User $reporter, ReportReason $reason, ?string $details): PostReport
    {
        if (Gate::forUser($reporter)->denies('report', $post)) {
            throw new AuthorizationException('You cannot report this post.');
        }

        $report = DB::transaction(function () use ($post, $reporter, $reason, $details): PostReport {
            $lockedPost = Post::query()
                ->with(['space', 'author'])
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (Gate::forUser($reporter)->denies('report', $lockedPost)) {
                throw new AuthorizationException('You cannot report this post.');
            }

            $report = PostReport::query()->firstOrCreate(
                [
                    'post_id' => $lockedPost->getKey(),
                    'reporter_id' => $reporter->getKey(),
                ],
                [
                    'space_id' => $lockedPost->space_id,
                    'reason' => $reason,
                    'details' => $details,
                    'status' => ReportStatus::Open,
                ],
            );

            if (! $report->wasRecentlyCreated) {
                throw ValidationException::withMessages([
                    'reason' => 'You have already reported this post.',
                ]);
            }

            return $report;
        });

        PostReported::dispatch($report);

        return $report;
    }

    public function moderate(
        Space $space,
        PostReport $report,
        User $moderator,
        ReportAction $action,
        ?string $note,
    ): PostReport {
        if (Gate::forUser($moderator)->denies('moderate', $space)
            || $report->space_id !== $space->getKey()) {
            throw new AuthorizationException('You cannot moderate this report.');
        }

        $report = DB::transaction(function () use ($space, $report, $moderator, $action, $note): PostReport {
            $lockedSpace = Space::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();
            $lockedReport = PostReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $lockedPost = Post::query()->whereKey($lockedReport->post_id)->lockForUpdate()->firstOrFail();

            if ($lockedReport->space_id !== $lockedSpace->getKey()
                || $lockedPost->space_id !== $lockedSpace->getKey()
                || Gate::forUser($moderator)->denies('moderate', $lockedSpace)) {
                throw new AuthorizationException('You cannot moderate this report.');
            }

            $this->applyAction($lockedReport, $lockedPost, $moderator, $action, $note);
            $this->recordAudit($lockedSpace, $lockedReport, $moderator, $action, $note);

            return $lockedReport->refresh();
        });

        PostReportModerated::dispatch($report, $action);

        return $report;
    }

    private function applyAction(
        PostReport $report,
        Post $post,
        User $moderator,
        ReportAction $action,
        ?string $note,
    ): void {
        $nextStatus = $this->workflow->nextStatus($report->status, $action);

        if ($action === ReportAction::Hide) {
            $post->forceFill([
                'hidden_at' => $post->hidden_at ?? now(),
                'hidden_by' => $post->hidden_by ?? $moderator->getKey(),
                'moderation_note' => $note,
            ])->save();
        }

        $hasOtherResolvedReports = $action === ReportAction::Reopen
            && PostReport::query()
                ->where('post_id', $post->getKey())
                ->whereKeyNot($report->getKey())
                ->where('status', ReportStatus::Resolved)
                ->exists();

        if ($action === ReportAction::Reopen
            && $post->hidden_at !== null
            && ! $hasOtherResolvedReports) {
            $post->forceFill([
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
        PostReport $report,
        User $moderator,
        ReportAction $action,
        ?string $note,
    ): void {
        SpaceAuditLog::query()->create([
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'action' => match ($action) {
                ReportAction::Review => SpaceAuditAction::PostReportReviewStarted,
                ReportAction::Hide => SpaceAuditAction::PostReportResolved,
                ReportAction::Dismiss => SpaceAuditAction::PostReportDismissed,
                ReportAction::Reopen => SpaceAuditAction::PostReportReopened,
            },
            'reason' => $note,
            'context' => [
                'report_id' => $report->getKey(),
                'post_id' => $report->post_id,
                'report_reason' => $report->reason->value,
            ],
        ]);
    }
}
