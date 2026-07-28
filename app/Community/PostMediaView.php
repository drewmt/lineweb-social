<?php

namespace App\Community;

use App\Models\Post;
use App\Models\PostMedia;

final class PostMediaView
{
    /** @return array{url: string, alt: string, width: int, height: int}|null */
    public function for(Post $post): ?array
    {
        $post->loadMissing('media');

        if (! $post->media instanceof PostMedia) {
            return null;
        }

        return [
            'url' => route('posts.image', $post),
            'alt' => $post->media->alt_text,
            'width' => $post->media->width,
            'height' => $post->media->height,
        ];
    }

    /**
     * @return list<array{id: int, url: string, alt: string, width: int, height: int}>
     */
    public function galleryFor(Post $post): array
    {
        $post->loadMissing('mediaItems');

        return array_values(
            $post->mediaItems->map(fn (PostMedia $media): array => [
                'id' => $media->id,
                'url' => route('posts.media.show', [
                    'post' => $post,
                    'media' => $media->id,
                ]),
                'alt' => $media->alt_text,
                'width' => $media->width,
                'height' => $media->height,
            ])
                ->all(),
        );
    }
}
