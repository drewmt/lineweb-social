<?php

namespace App\Community;

use App\Models\Post;
use App\Models\PostVideo;
use App\Models\User;

final class PostVideoView
{
    /** @return array{url: string, posterUrl: string, description: string, durationMs: int, width: int, height: int}|null */
    public function for(Post $post, User $viewer, bool $api = false): ?array
    {
        if ($post->hidden_at !== null || ! $viewer->can('view', $post)) {
            return null;
        }

        $post->loadMissing('video');
        $video = $post->video;

        if (! $video instanceof PostVideo || $video->status !== PostVideo::STATUS_READY) {
            return null;
        }

        return [
            'url' => route($api ? 'api.v1.posts.video' : 'posts.video', $post),
            'posterUrl' => route($api ? 'api.v1.posts.video.poster' : 'posts.video.poster', $post),
            'description' => $video->description,
            'durationMs' => (int) $video->duration_ms,
            'width' => (int) $video->width,
            'height' => (int) $video->height,
        ];
    }
}
