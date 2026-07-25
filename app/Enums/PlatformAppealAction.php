<?php

namespace App\Enums;

enum PlatformAppealAction: string
{
    case Review = 'review';
    case Approve = 'approve';
    case Deny = 'deny';
}
