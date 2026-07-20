<?php

namespace App\Http\Requests;

use App\Enums\SpaceVisibility;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Space::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', Rule::enum(SpaceVisibility::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Give your space a name.',
            'description.max' => 'Descriptions can be up to 500 characters.',
            'visibility.required' => 'Choose who can discover this space.',
        ];
    }
}
