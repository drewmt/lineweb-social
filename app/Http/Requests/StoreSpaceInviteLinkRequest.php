<?php

namespace App\Http\Requests;

use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpaceInviteLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');

        return $space instanceof Space
            && $this->user()?->can('createInviteLink', $space) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:80'],
            'expires_in_days' => ['required', 'integer', 'min:1', 'max:30'],
            'max_uses' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
