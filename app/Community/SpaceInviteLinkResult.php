<?php

namespace App\Community;

use App\Models\SpaceInviteLink;

final readonly class SpaceInviteLinkResult
{
    public function __construct(
        public SpaceInviteLink $inviteLink,
        public string $token,
    ) {}
}
