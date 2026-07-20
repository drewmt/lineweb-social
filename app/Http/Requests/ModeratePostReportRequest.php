<?php

namespace App\Http\Requests;

use App\Enums\ReportAction;
use App\Models\PostReport;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModeratePostReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');
        $report = $this->route('postReport');

        return $space instanceof Space
            && $report instanceof PostReport
            && $report->space_id === $space->getKey()
            && $this->user()?->can('moderate', $space) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(ReportAction::class)],
            'note' => [
                Rule::requiredIf($this->input('action') !== ReportAction::Review->value),
                'nullable',
                'string',
                'min:3',
                'max:500',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => 'Add a short moderator note before making this decision.',
            'note.min' => 'The moderator note must be at least 3 characters.',
            'note.max' => 'Moderator notes can be up to 500 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $note = trim((string) $this->input('note'));

        $this->merge(['note' => $note !== '' ? $note : null]);
    }
}
