<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'comment_replies' => ['required', 'boolean'],
            'space_moderation' => ['required', 'boolean'],
        ];
    }
}
