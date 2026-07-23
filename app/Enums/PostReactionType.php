<?php

namespace App\Enums;

enum PostReactionType: string
{
    case Like = 'like';
    case Celebrate = 'celebrate';
    case Insightful = 'insightful';

    public function label(): string
    {
        return match ($this) {
            self::Like => 'Like',
            self::Celebrate => 'Celebrate',
            self::Insightful => 'Insightful',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
