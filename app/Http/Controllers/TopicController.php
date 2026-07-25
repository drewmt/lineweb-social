<?php

namespace App\Http\Controllers;

use App\Community\CommunityFeed;
use App\Community\VisiblePostQuery;
use App\Enums\PostReactionType;
use App\Enums\ReportReason;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function __invoke(
        Request $request,
        Topic $topic,
        CommunityFeed $feed,
        VisiblePostQuery $visiblePosts,
    ): Response {
        /** @var User $viewer */
        $viewer = $request->user();

        return Inertia::render('feed/index', [
            'spaces' => $feed->spaces($viewer),
            'posts' => $feed->posts($viewer, topic: $topic),
            'reportReasons' => ReportReason::options(),
            'reactionTypes' => PostReactionType::options(),
            'selectedSpace' => null,
            'viewMode' => 'topic',
            'topic' => [
                'name' => $topic->name,
                'url' => route('topics.show', $topic),
                'visiblePostCount' => $visiblePosts
                    ->forTopic($viewer, $topic)
                    ->count(),
            ],
        ]);
    }
}
