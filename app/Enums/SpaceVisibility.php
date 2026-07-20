<?php

namespace App\Enums;

enum SpaceVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Hidden = 'hidden';
}
