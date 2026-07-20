<?php

namespace App\Http\Controllers;

use App\Community\ManageCommentReports;
use App\Enums\ReportReason;
use App\Http\Requests\StoreCommentReportRequest;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CommentReportController extends Controller
{
    public function store(
        StoreCommentReportRequest $request,
        Comment $comment,
        ManageCommentReports $reports,
    ): RedirectResponse {
        /** @var User $reporter */
        $reporter = $request->user();

        $reports->submit(
            $comment,
            $reporter,
            ReportReason::from($request->string('reason')->toString()),
            $request->filled('details') ? $request->string('details')->toString() : null,
        );

        return back()->with('status', 'Thanks. The Space moderation team can now review this report.');
    }
}
