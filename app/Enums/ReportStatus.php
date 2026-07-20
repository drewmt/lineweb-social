<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Reviewing => 'In review',
            self::Resolved => 'Content hidden',
            self::Dismissed => 'Dismissed',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Reviewing], true);
    }
}
