<?php

namespace Tests\Unit;

use App\Community\Topics\TopicParser;
use PHPUnit\Framework\TestCase;

class TopicParserTest extends TestCase
{
    public function test_it_extracts_unique_case_insensitive_unicode_topics(): void
    {
        $topics = (new TopicParser)->names(
            'Build with #Laravel, #LARAVEL, #Open_Source, #maker-tools and #Ελλάδα.',
        );

        $this->assertSame([
            'laravel',
            'open_source',
            'maker-tools',
            'ελλάδα',
        ], $topics);
    }

    public function test_it_ignores_embedded_fragments_short_values_and_oversized_topics(): void
    {
        $oversized = str_repeat('a', TopicParser::MAX_LENGTH + 1);

        $topics = (new TopicParser)->names(
            "word#inside https://example.com/#fragment #a #{$oversized} (#valid-topic)",
        );

        $this->assertSame(['valid-topic'], $topics);
    }

    public function test_it_caps_topics_per_post_in_body_order(): void
    {
        $body = collect(range(1, TopicParser::MAX_TOPICS + 3))
            ->map(fn (int $number): string => "#topic{$number}")
            ->implode(' ');

        $topics = (new TopicParser)->names($body);

        $this->assertCount(TopicParser::MAX_TOPICS, $topics);
        $this->assertSame('topic1', $topics[0]);
        $this->assertSame('topic10', $topics[9]);
    }
}
