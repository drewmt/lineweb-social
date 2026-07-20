<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post
            && $this->user()?->can('report', $post) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => [
                Rule::requiredIf($this->input('reason') === ReportReason::Other->value),
                'nullable',
                'string',
                'max:750',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'details.required' => 'Please add a short explanation for this report.',
            'details.max' => 'Report details can be up to 750 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $details = trim((string) $this->input('details'));

        $this->merge(['details' => $details !== '' ? $details : null]);
    }
}
