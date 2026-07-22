<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'abilities' => ['required', 'array', 'min:1', 'max:4'],
            'abilities.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(['profile:read', 'profiles:read', 'spaces:read', 'feed:read']),
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $abilities = $this->input('abilities');

            if (is_array($abilities) && ! in_array('profile:read', $abilities, true)) {
                $validator->errors()->add(
                    'abilities',
                    'Every API token must include own-profile access.',
                );
            }
        }];
    }
}
