<?php

namespace App\Http\Requests;

use App\Models\Space;
use App\Models\SpaceEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreSpaceEventRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->string('title')->trim()->toString(),
            'description' => $this->filled('description')
                ? $this->string('description')->trim()->toString()
                : null,
            'venue' => $this->filled('venue')
                ? $this->string('venue')->trim()->toString()
                : null,
            'online_url' => $this->filled('online_url')
                ? $this->string('online_url')->trim()->toString()
                : null,
            'capacity' => $this->filled('capacity') ? $this->input('capacity') : null,
        ]);
    }

    public function authorize(): bool
    {
        $space = $this->route('space');

        return $space instanceof Space
            && Gate::forUser($this->user())->allows('create', [SpaceEvent::class, $space]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'venue' => ['nullable', 'string', 'max:160'],
            'online_url' => ['nullable', 'url:http,https', 'max:2048'],
            'capacity' => ['nullable', 'integer', 'min:2', 'max:10000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $timezone = $this->input('timezone');
            $startsAt = $this->localDate('starts_at', $timezone);
            $endsAt = $this->localDate('ends_at', $timezone);

            foreach (['starts_at' => $startsAt, 'ends_at' => $endsAt] as $field => $date) {
                if ($this->filled($field) && $date === null && ! $validator->errors()->has($field)) {
                    $validator->errors()->add($field, 'Choose a valid local date and time.');
                }
            }

            if ($startsAt !== null && $startsAt->lessThanOrEqualTo(now()->addMinutes(5))) {
                $validator->errors()->add('starts_at', 'Choose a start time at least five minutes from now.');
            }

            if ($startsAt !== null && $endsAt !== null) {
                if ($endsAt->lessThanOrEqualTo($startsAt)) {
                    $validator->errors()->add('ends_at', 'The end time must be after the start time.');
                } elseif ($endsAt->greaterThan($startsAt->addDays(7))) {
                    $validator->errors()->add('ends_at', 'An event can run for up to seven days.');
                }
            }

            if (! $this->filled('venue') && ! $this->filled('online_url')) {
                $validator->errors()->add('venue', 'Add a venue or a secure online event link.');
            }

            if ($this->filled('online_url') && strtolower((string) parse_url((string) $this->input('online_url'), PHP_URL_SCHEME)) !== 'https') {
                $validator->errors()->add('online_url', 'Online event links must use HTTPS.');
            }
        }];
    }

    /**
     * @return array{title: string, description: string|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable, timezone: string, venue: string|null, online_url: string|null, capacity: int|null}
     */
    public function eventAttributes(): array
    {
        $timezone = $this->string('timezone')->toString();

        return [
            'title' => $this->string('title')->toString(),
            'description' => $this->input('description'),
            'starts_at' => CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $this->string('starts_at')->toString(), $timezone)->utc(),
            'ends_at' => CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $this->string('ends_at')->toString(), $timezone)->utc(),
            'timezone' => $timezone,
            'venue' => $this->input('venue'),
            'online_url' => $this->input('online_url'),
            'capacity' => $this->filled('capacity') ? $this->integer('capacity') : null,
        ];
    }

    private function localDate(string $field, mixed $timezone): ?CarbonImmutable
    {
        if (! is_string($timezone) || $timezone === '' || ! is_string($this->input($field))) {
            return null;
        }

        try {
            $value = (string) $this->input($field);
            $date = CarbonImmutable::createFromFormat(
                'Y-m-d\\TH:i',
                $value,
                $timezone,
            );

            return $date->format('Y-m-d\\TH:i') === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
