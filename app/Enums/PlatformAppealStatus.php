<?php

namespace App\Enums;

enum PlatformAppealStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Approved = 'approved';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Submitted',
            self::Reviewing => 'In review',
            self::Approved => 'Approved',
            self::Denied => 'Not approved',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Reviewing], true);
    }
}
