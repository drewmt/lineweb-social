<?php

namespace App\Http\Controllers;

use App\Community\CommunityFeed;
use App\Enums\ReportReason;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __invoke(Request $request, CommunityFeed $feed): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('feed/index', [
            'spaces' => $feed->spaces($user),
            'posts' => $feed->posts($user),
            'reportReasons' => ReportReason::options(),
            'selectedSpace' => null,
        ]);
    }
}
