<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $viewer, Conversation $conversation): bool
    {
        return $conversation->includes($viewer);
    }

    public function send(User $sender, Conversation $conversation): bool
    {
        return $this->view($sender, $conversation)
            && ! $sender->isBlockedWith($conversation->otherParticipant($sender));
    }
}
