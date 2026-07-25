<?php

namespace App\Http\Requests;

use App\Enums\PlatformAppealAction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModeratePlatformAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User
            && $this->user()->isAdministrator();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(PlatformAppealAction::class)],
            'decision_message' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision_message.required' => 'Write the account holder a clear decision message.',
            'decision_message.min' => 'The decision message must be at least 10 characters.',
            'decision_message.max' => 'The decision message can be up to 500 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => trim((string) $this->input('action')),
            'decision_message' => trim((string) $this->input('decision_message')),
        ]);
    }
}
