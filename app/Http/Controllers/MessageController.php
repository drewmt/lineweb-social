<?php

namespace App\Http\Controllers;

use App\Community\PrivateMessaging;
use App\Http\Requests\SendDirectMessageRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request, PrivateMessaging $messages): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return Inertia::render('messages/index', [
            'conversations' => $messages->inboxFor($viewer),
            'active' => null,
            'composeTarget' => null,
        ]);
    }

    public function compose(
        Request $request,
        User $profile,
        PrivateMessaging $messages,
    ): Response {
        /** @var User $viewer */
        $viewer = $request->user();
        Gate::forUser($viewer)->authorize('message', $profile);

        return Inertia::render('messages/index', [
            'conversations' => $messages->inboxFor($viewer),
            'active' => null,
            'composeTarget' => [
                'name' => $profile->name,
                'handle' => $profile->handle,
            ],
        ]);
    }

    public function start(
        SendDirectMessageRequest $request,
        User $profile,
        PrivateMessaging $messages,
    ): RedirectResponse {
        /** @var User $viewer */
        $viewer = $request->user();
        $conversation = $messages->start(
            $viewer,
            $profile,
            $request->string('body')->toString(),
        );

        return redirect()
            ->route('messages.show', $conversation)
            ->with('status', 'Message sent.');
    }

    public function show(
        Request $request,
        Conversation $conversation,
        PrivateMessaging $messages,
    ): Response {
        /** @var User $viewer */
        $viewer = $request->user();

        return Inertia::render('messages/index', [
            'conversations' => $messages->inboxFor($viewer),
            'active' => $messages->threadFor($viewer, $conversation),
            'composeTarget' => null,
        ]);
    }

    public function store(
        SendDirectMessageRequest $request,
        Conversation $conversation,
        PrivateMessaging $messages,
    ): RedirectResponse {
        /** @var User $viewer */
        $viewer = $request->user();
        $messages->send(
            $viewer,
            $conversation,
            $request->string('body')->toString(),
        );

        return back()->with('status', 'Message sent.');
    }

    public function read(
        Request $request,
        Conversation $conversation,
        PrivateMessaging $messages,
    ): RedirectResponse {
        /** @var User $viewer */
        $viewer = $request->user();
        $messages->markRead($viewer, $conversation);

        return back();
    }
}
