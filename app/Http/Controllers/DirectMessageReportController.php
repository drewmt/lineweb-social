<?php

namespace App\Http\Controllers;

use App\Community\ManageDirectMessageReports;
use App\Enums\ReportReason;
use App\Http\Requests\StoreDirectMessageReportRequest;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DirectMessageReportController extends Controller
{
    public function store(
        StoreDirectMessageReportRequest $request,
        Conversation $conversation,
        DirectMessage $directMessage,
        ManageDirectMessageReports $reports,
    ): RedirectResponse {
        /** @var User $reporter */
        $reporter = $request->user();

        $reports->submit(
            $conversation,
            $directMessage,
            $reporter,
            ReportReason::from($request->string('reason')->toString()),
            $request->filled('details')
                ? $request->string('details')->toString()
                : null,
        );

        return back()->with(
            'status',
            'Thanks. A platform administrator can now review this message report.',
        );
    }
}
