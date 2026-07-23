<?php

namespace App\Http\Controllers;

use App\Community\CommunityFeed;
use App\Enums\PostReactionType;
use App\Enums\ReportReason;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowingFeedController extends Controller
{
    public function __invoke(Request $request, CommunityFeed $feed): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('feed/index', [
            'spaces' => $feed->spaces($user),
            'posts' => $feed->posts($user, followingOnly: true),
            'reportReasons' => ReportReason::options(),
            'reactionTypes' => PostReactionType::options(),
            'selectedSpace' => null,
            'viewMode' => 'following',
        ]);
    }
}
