<?php

namespace App\Enums;

enum UserRelationshipType: string
{
    case Mute = 'mute';
    case Block = 'block';
}
