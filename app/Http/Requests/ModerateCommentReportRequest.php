<?php

namespace App\Http\Requests;

use App\Enums\ReportAction;
use App\Models\CommentReport;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateCommentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');
        $report = $this->route('commentReport');

        return $space instanceof Space
            && $report instanceof CommentReport
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

    protected function prepareForValidation(): void
    {
        $note = trim((string) $this->input('note'));

        $this->merge(['note' => $note !== '' ? $note : null]);
    }
}
