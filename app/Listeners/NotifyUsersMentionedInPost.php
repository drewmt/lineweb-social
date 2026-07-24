<?php

namespace App\Listeners;

use App\Community\Mentions\MentionNotifier;
use App\Events\PostPublished;

class NotifyUsersMentionedInPost
{
    public function __construct(private readonly MentionNotifier $mentions) {}

    public function handle(PostPublished $event): void
    {
        $this->mentions->forPost($event->post);
    }
}
