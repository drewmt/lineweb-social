<?php

namespace App\Http\Requests;

use App\Enums\NotificationDigestFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'comment_replies' => ['required', 'boolean'],
            'content_mentions' => ['required', 'boolean'],
            'space_moderation' => ['required', 'boolean'],
            'email_digest_frequency' => [
                'required',
                Rule::enum(NotificationDigestFrequency::class),
            ],
        ];
    }
}
