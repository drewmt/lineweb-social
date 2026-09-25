<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class MediaWorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue((string) config('media.video.queue', 'media'));
    }

    public static function cacheKey(): string
    {
        return 'media-worker-heartbeat:'.hash('sha256',
            (string) config('app.url').'|'.(string) config('media.video.queue', 'media'),
        );
    }

    public function handle(): void
    {
        Cache::put(self::cacheKey(), now()->timestamp, now()->addMinutes(5));
    }
}
