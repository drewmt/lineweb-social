<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
        ]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'abilities' => ['required', 'array', 'min:1', 'max:3'],
            'abilities.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(['profile:read', 'profiles:read', 'spaces:read']),
            ],
        ];
    }
}
