<?php

namespace App\Http\Controllers;

use App\Community\ReelsFeed;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReelsController extends Controller
{
    public function __invoke(Request $request, ReelsFeed $feed): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $cursor = $request->query('cursor');
        if ($cursor !== null && ! is_string($cursor)) {
            throw ValidationException::withMessages(['cursor' => 'The Reels cursor is invalid.']);
        }
        $page = $feed->page($viewer, is_string($cursor) ? $cursor : null);

        return Inertia::render('reels/index', $page);
    }
}
