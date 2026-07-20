<?php

namespace App\Enums;

enum ReportAction: string
{
    case Review = 'review';
    case Hide = 'hide';
    case Dismiss = 'dismiss';
    case Reopen = 'reopen';
}
