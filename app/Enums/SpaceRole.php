<?php

namespace App\Enums;

enum SpaceRole: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Member = 'member';
}
