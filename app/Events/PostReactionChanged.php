<?php

namespace App\Events;

use App\Enums\PostReactionType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostReactionChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Post $post,
        public readonly User $user,
        public readonly ?PostReactionType $previousType,
        public readonly ?PostReactionType $type,
    ) {}
}
