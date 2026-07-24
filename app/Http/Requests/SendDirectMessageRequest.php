<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }
}
