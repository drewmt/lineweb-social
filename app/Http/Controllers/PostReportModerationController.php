<?php

namespace App\Http\Controllers;

use App\Community\ManagePostReports;
use App\Enums\ReportAction;
use App\Http\Requests\ModeratePostReportRequest;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PostReportModerationController extends Controller
{
    public function update(
        ModeratePostReportRequest $request,
        Space $space,
        PostReport $postReport,
        ManagePostReports $reports,
    ): RedirectResponse {
        /** @var User $moderator */
        $moderator = $request->user();
        $action = ReportAction::from($request->string('action')->toString());

        $reports->moderate(
            $space,
            $postReport,
            $moderator,
            $action,
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return back()->with('status', match ($action) {
            ReportAction::Review => 'The report is now in review.',
            ReportAction::Hide => 'The post was hidden and the decision was recorded.',
            ReportAction::Dismiss => 'The report was dismissed and the decision was recorded.',
            ReportAction::Reopen => 'The report was reopened. The post is restored when no other removal decision remains.',
        });
    }
}
