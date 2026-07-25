<?php

namespace App\Enums;

enum DirectMessageReportAction: string
{
    case Review = 'review';
    case Resolve = 'resolve';
    case Dismiss = 'dismiss';
    case Reopen = 'reopen';
}
