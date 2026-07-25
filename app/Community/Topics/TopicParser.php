<?php

namespace App\Community\Topics;

final class TopicParser
{
    public const MAX_TOPICS = 10;

    public const MAX_LENGTH = 50;

    private const MIN_LENGTH = 2;

    /** @return list<string> */
    public function names(string $body): array
    {
        preg_match_all(
            '/(?<![\p{L}\p{N}._\/#@&?=-])#([\p{L}\p{N}](?:[\p{L}\p{N}\p{M}_-]{0,48}[\p{L}\p{N}\p{M}_])?)(?![\p{L}\p{N}\p{M}_-])/u',
            $body,
            $matches,
        );

        $topics = [];

        foreach ($matches[1] as $match) {
            $name = mb_strtolower((string) $match);
            $length = mb_strlen($name);

            if ($length < self::MIN_LENGTH
                || $length > self::MAX_LENGTH
                || in_array($name, $topics, true)) {
                continue;
            }

            $topics[] = $name;

            if (count($topics) === self::MAX_TOPICS) {
                break;
            }
        }

        return $topics;
    }
}
