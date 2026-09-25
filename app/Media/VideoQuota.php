<?php

namespace App\Media;

use App\Models\PostVideo;

final class VideoQuota
{
    /**
     * Call inside a transaction after locking the Space row. A locking read
     * sees the latest committed rows even when an earlier eager load created
     * a repeatable-read snapshot before the Space lock was acquired.
     */
    public function usedBytes(int $spaceId, ?int $exceptVideoId = null): int
    {
        $videos = PostVideo::query()
            ->where('space_id', $spaceId)
            ->when($exceptVideoId !== null, fn ($query) => $query->whereKeyNot($exceptVideoId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'reserved_bytes', 'output_bytes']);

        return (int) $videos->sum(
            static fn (PostVideo $video): int => (int) $video->reserved_bytes + (int) $video->output_bytes,
        );
    }
}
