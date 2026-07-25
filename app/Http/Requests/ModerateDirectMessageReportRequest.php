<?php

namespace App\Http\Requests;

use App\Enums\DirectMessageReportAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateDirectMessageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(DirectMessageReportAction::class)],
            'note' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => 'Record a reason for this administrator action.',
            'note.min' => 'The administrator note must be at least 10 characters.',
            'note.max' => 'The administrator note can be up to 500 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => trim((string) $this->input('action')),
            'note' => trim((string) $this->input('note')),
        ]);
    }
}
