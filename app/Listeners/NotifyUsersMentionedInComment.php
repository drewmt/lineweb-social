<?php

namespace App\Listeners;

use App\Community\Mentions\MentionNotifier;
use App\Events\CommentPublished;

class NotifyUsersMentionedInComment
{
    public function __construct(private readonly MentionNotifier $mentions) {}

    public function handle(CommentPublished $event): void
    {
        $this->mentions->forComment($event->comment);
    }
}
