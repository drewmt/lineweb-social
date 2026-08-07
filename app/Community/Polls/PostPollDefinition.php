<?php

namespace App\Community\Polls;

final readonly class PostPollDefinition
{
    /** @var list<string> */
    public array $options;

    /**
     * @param  list<string>  $options
     */
    public function __construct(
        public string $question,
        array $options,
        public ?int $closesAfterDays,
    ) {
        $this->options = $options;
    }
}
