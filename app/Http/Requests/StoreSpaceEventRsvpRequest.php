<?php

namespace App\Http\Requests;

use App\Enums\SpaceEventRsvpStatus;
use App\Models\SpaceEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpaceEventRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('spaceEvent');

        return $event instanceof SpaceEvent
            && $this->user()?->can('rsvp', $event) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SpaceEventRsvpStatus::class)],
        ];
    }
}
