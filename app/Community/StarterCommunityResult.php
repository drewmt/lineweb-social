<?php

namespace App\Community;

use App\Models\Space;

final readonly class StarterCommunityResult
{
    public function __construct(
        public Space $space,
        public bool $created,
    ) {}
}
