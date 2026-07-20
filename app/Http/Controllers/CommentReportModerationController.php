<?php

namespace App\Http\Controllers;

use App\Community\ManageCommentReports;
use App\Enums\ReportAction;
use App\Http\Requests\ModerateCommentReportRequest;
use App\Models\CommentReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CommentReportModerationController extends Controller
{
    public function update(
        ModerateCommentReportRequest $request,
        Space $space,
        CommentReport $commentReport,
        ManageCommentReports $reports,
    ): RedirectResponse {
        /** @var User $moderator */
        $moderator = $request->user();
        $action = ReportAction::from($request->string('action')->toString());

        $reports->moderate(
            $space,
            $commentReport,
            $moderator,
            $action,
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return back()->with('status', match ($action) {
            ReportAction::Review => 'The report is now in review.',
            ReportAction::Hide => 'The comment was hidden and the decision was recorded.',
            ReportAction::Dismiss => 'The report was dismissed and the decision was recorded.',
            ReportAction::Reopen => 'The report was reopened. The comment is restored when no other removal decision remains.',
        });
    }
}
