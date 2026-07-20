<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case HateSpeech = 'hate_speech';
    case DangerousContent = 'dangerous_content';
    case Privacy = 'privacy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam or misleading',
            self::Harassment => 'Harassment or bullying',
            self::HateSpeech => 'Hateful content',
            self::DangerousContent => 'Dangerous or harmful content',
            self::Privacy => 'Privacy concern',
            self::Other => 'Something else',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $reason): array => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            self::cases(),
        );
    }
}
