<?php

namespace App\Http\Requests\Concerns;

use App\Community\Polls\PostPollDefinition;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait HandlesPostPoll
{
    /** @return array<string, array<int, mixed>> */
    protected function postPollRules(): array
    {
        return [
            'poll_question' => ['nullable', 'string', 'max:180'],
            'poll_options' => ['nullable', 'array', 'max:4'],
            'poll_options.*' => ['nullable', 'string', 'max:100'],
            'poll_duration' => ['nullable', 'integer', Rule::in([1, 3, 7])],
        ];
    }

    protected function preparePostPollForValidation(): void
    {
        $options = $this->input('poll_options', []);

        $this->merge([
            'poll_question' => trim((string) $this->input('poll_question')),
            'poll_options' => is_array($options)
                ? array_values(array_map(
                    static fn (mixed $option): mixed => is_string($option) ? trim($option) : $option,
                    $options,
                ))
                : $options,
            'poll_duration' => $this->filled('poll_duration')
                ? $this->input('poll_duration')
                : null,
        ]);
    }

    protected function validatePostPoll(Validator $validator, string $missingContentMessage): void
    {
        $question = trim((string) $this->input('poll_question'));
        $options = $this->input('poll_options', []);
        $options = is_array($options) ? $options : [];
        $hasPollInput = $question !== ''
            || $options !== []
            || $this->filled('poll_duration');

        if (! $hasPollInput) {
            if (trim((string) $this->input('body')) === '') {
                $validator->errors()->add('body', $missingContentMessage);
            }

            return;
        }

        if ($question === '') {
            $validator->errors()->add('poll_question', 'Add a question for this poll.');
        }

        $labels = array_values(array_filter(
            $options,
            static fn (mixed $option): bool => is_string($option) && $option !== '',
        ));

        if (count($labels) < 2) {
            $validator->errors()->add('poll_options', 'Add at least two poll options.');
        }

        if (count($labels) !== count($options)) {
            $validator->errors()->add('poll_options', 'Every poll option needs text.');
        }

        $normalized = array_map(
            static fn (string $label): string => mb_strtolower($label),
            $labels,
        );

        if (count($normalized) !== count(array_unique($normalized))) {
            $validator->errors()->add('poll_options', 'Use distinct poll options.');
        }
    }

    public function pollDefinition(): ?PostPollDefinition
    {
        $question = trim((string) $this->input('poll_question'));

        if ($question === '') {
            return null;
        }

        $options = $this->input('poll_options', []);
        $options = is_array($options) ? $options : [];
        $duration = $this->input('poll_duration');

        return new PostPollDefinition(
            $question,
            array_values(array_map(
                static fn (mixed $option): string => trim((string) $option),
                $options,
            )),
            is_numeric($duration) ? (int) $duration : null,
        );
    }
}
