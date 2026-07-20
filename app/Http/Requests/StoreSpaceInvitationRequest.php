<?php

namespace App\Http\Requests;

use App\Enums\SpaceRole;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpaceInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');

        return $space instanceof Space
            && $this->user()?->can('moderate', $space) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'role' => ['required', Rule::in([SpaceRole::Member->value, SpaceRole::Moderator->value])],
        ];
    }
}
