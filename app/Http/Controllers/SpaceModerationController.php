<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Models\CommentReport;
use App\Models\PostReport;
use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SpaceModerationController extends Controller
{
    public function __invoke(Request $request, Space $space): Response
    {
        Gate::authorize('moderate', $space);

        $postReports = $space->postReports()
            ->with([
                'post.author:id,name,handle',
                'reporter:id,name',
                'reviewer:id,name',
            ])
            ->get()
            ->map(fn (PostReport $report): array => [
                'contentType' => 'post',
                'contentLabel' => 'Post',
                'actionUrl' => route('spaces.moderation.reports.update', [$space, $report]),
                'id' => $report->getKey(),
                'reason' => $report->reason->value,
                'reasonLabel' => $report->reason->label(),
                'details' => $report->details,
                'status' => $report->status->value,
                'statusLabel' => $report->status->label(),
                'reporter' => $report->reporter !== null
                    ? ['name' => $report->reporter->name]
                    : null,
                'reviewer' => $report->reviewer?->name,
                'moderatorNote' => $report->moderator_note,
                'createdAt' => $report->created_at->toIso8601String(),
                'reviewedAt' => $report->reviewed_at?->toIso8601String(),
                'content' => [
                    'id' => $report->post->getKey(),
                    'body' => Str::limit($report->post->body, 600),
                    'author' => [
                        'name' => $report->post->author->name,
                        'handle' => $report->post->author->handle,
                    ],
                    'hiddenAt' => $report->post->hidden_at?->toIso8601String(),
                    'postContext' => null,
                ],
            ]);

        $commentReports = $space->commentReports()
            ->with([
                'comment.author:id,name,handle',
                'comment.post:id,space_id,body',
                'reporter:id,name',
                'reviewer:id,name',
            ])
            ->get()
            ->map(fn (CommentReport $report): array => [
                'contentType' => 'comment',
                'contentLabel' => 'Comment',
                'actionUrl' => route('spaces.moderation.comment-reports.update', [$space, $report]),
                'id' => $report->getKey(),
                'reason' => $report->reason->value,
                'reasonLabel' => $report->reason->label(),
                'details' => $report->details,
                'status' => $report->status->value,
                'statusLabel' => $report->status->label(),
                'reporter' => $report->reporter !== null
                    ? ['name' => $report->reporter->name]
                    : null,
                'reviewer' => $report->reviewer?->name,
                'moderatorNote' => $report->moderator_note,
                'createdAt' => $report->created_at->toIso8601String(),
                'reviewedAt' => $report->reviewed_at?->toIso8601String(),
                'content' => [
                    'id' => $report->comment->getKey(),
                    'body' => Str::limit($report->comment->body, 600),
                    'author' => [
                        'name' => $report->comment->author->name,
                        'handle' => $report->comment->author->handle,
                    ],
                    'hiddenAt' => $report->comment->hidden_at?->toIso8601String(),
                    'postContext' => [
                        'id' => $report->comment->post->getKey(),
                        'body' => Str::limit($report->comment->post->body, 180),
                    ],
                ],
            ]);

        $reports = $postReports
            ->concat($commentReports)
            ->sortByDesc('createdAt')
            ->take(50)
            ->values()
            ->all();

        $postCounts = $space->postReports()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $commentCounts = $space->commentReports()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $count = fn (ReportStatus $status): int => (int) $postCounts->get($status->value, 0)
            + (int) $commentCounts->get($status->value, 0);

        return Inertia::render('spaces/moderation', [
            'space' => [
                'name' => $space->name,
                'slug' => $space->slug,
            ],
            'reports' => $reports,
            'counts' => [
                'active' => $count(ReportStatus::Open) + $count(ReportStatus::Reviewing),
                'resolved' => $count(ReportStatus::Resolved),
                'dismissed' => $count(ReportStatus::Dismissed),
            ],
        ]);
    }
}
