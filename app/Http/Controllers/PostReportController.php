<?php

namespace App\Http\Controllers;

use App\Community\ManagePostReports;
use App\Enums\ReportReason;
use App\Http\Requests\StorePostReportRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PostReportController extends Controller
{
    public function store(
        StorePostReportRequest $request,
        Post $post,
        ManagePostReports $reports,
    ): RedirectResponse {
        /** @var User $reporter */
        $reporter = $request->user();

        $reports->submit(
            $post,
            $reporter,
            ReportReason::from($request->string('reason')->toString()),
            $request->filled('details') ? $request->string('details')->toString() : null,
        );

        return back()->with('status', 'Thanks. The Space moderation team can now review this report.');
    }
}
