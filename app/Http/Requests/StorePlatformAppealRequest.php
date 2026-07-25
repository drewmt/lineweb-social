<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePlatformAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'statement' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'statement.required' => 'Explain why you believe this restriction should be reviewed.',
            'statement.min' => 'Your appeal must be at least 20 characters.',
            'statement.max' => 'Your appeal can be up to 2,000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'statement' => trim((string) $this->input('statement')),
        ]);
    }
}
