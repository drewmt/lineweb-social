<?php

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;

class PruneExpiredStories extends Command
{
    protected $signature = 'stories:prune';

    protected $description = 'Permanently delete expired Stories and their private media';

    public function handle(): int
    {
        $deleted = 0;

        Story::query()
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->eachById(function (Story $story) use (&$deleted): void {
                $story->delete();
                $deleted++;
            });

        $this->info("Pruned {$deleted} expired Story record(s).");

        return self::SUCCESS;
    }
}
