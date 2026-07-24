<?php

namespace App\Community\Mentions;

final class MentionParser
{
    public const MAX_MENTIONS = 10;

    /** @return list<string> */
    public function handles(string $body): array
    {
        preg_match_all(
            '/(?<![a-z0-9._\/@-])@([a-z0-9]+(?:-[a-z0-9]+)*)\b/i',
            $body,
            $matches,
        );

        $handles = [];

        foreach ($matches[1] as $match) {
            $handle = strtolower($match);

            if (strlen($handle) < 3
                || strlen($handle) > 40
                || in_array($handle, $handles, true)) {
                continue;
            }

            $handles[] = $handle;

            if (count($handles) === self::MAX_MENTIONS) {
                break;
            }
        }

        return $handles;
    }
}
