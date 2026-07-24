<?php

namespace Tests\Unit;

use App\Community\Mentions\MentionParser;
use PHPUnit\Framework\TestCase;

class MentionParserTest extends TestCase
{
    public function test_it_extracts_unique_normalized_handles_without_matching_emails_or_urls(): void
    {
        $parser = new MentionParser;

        $this->assertSame(
            ['andrew-matia', 'maker-42'],
            $parser->handles(
                'Thanks @Andrew-Matia, @maker-42 and again @andrew-matia. '
                .'Ignore hello@example.com and https://example.com/@hidden-user.',
            ),
        );
    }

    public function test_it_bounds_the_number_of_mentions_in_one_body(): void
    {
        $parser = new MentionParser;
        $body = collect(range(1, 25))
            ->map(fn (int $index): string => '@member-'.$index)
            ->implode(' ');

        $this->assertCount(MentionParser::MAX_MENTIONS, $parser->handles($body));
    }
}
