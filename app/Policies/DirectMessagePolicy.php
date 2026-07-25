<?php

namespace App\Policies;

use App\Models\DirectMessage;
use App\Models\User;

class DirectMessagePolicy
{
    public function report(User $reporter, DirectMessage $message): bool
    {
        $message->loadMissing('conversation');

        return $message->sender_id !== $reporter->getKey()
            && $message->conversation->includes($reporter);
    }
}
