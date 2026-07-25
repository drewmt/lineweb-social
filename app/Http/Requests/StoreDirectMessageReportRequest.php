<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use App\Models\Conversation;
use App\Models\DirectMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDirectMessageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');
        $message = $this->route('directMessage');

        return $conversation instanceof Conversation
            && $message instanceof DirectMessage
            && $message->conversation_id === $conversation->getKey()
            && $this->user()?->can('report', $message) === true;
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
