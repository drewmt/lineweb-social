<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Enums\ProfileVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'handle' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('users', 'handle')->ignore($this->user()->id),
            ],
            'headline' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:320'],
            'location' => ['nullable', 'string', 'max:120'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'profile_visibility' => ['required', Rule::enum(ProfileVisibility::class)],
            'is_discoverable' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'handle' => str($this->input('handle'))->trim()->lower()->toString(),
            'headline' => $this->filled('headline') ? str($this->input('headline'))->trim()->toString() : null,
            'bio' => $this->filled('bio') ? str($this->input('bio'))->trim()->toString() : null,
            'location' => $this->filled('location') ? str($this->input('location'))->trim()->toString() : null,
            'website_url' => $this->filled('website_url') ? str($this->input('website_url'))->trim()->toString() : null,
        ]);
    }
}
