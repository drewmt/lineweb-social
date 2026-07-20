<?php

namespace App\Http\Requests;

use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpaceMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');
        $member = $this->route('member');

        return $space instanceof Space
            && $member instanceof User
            && $this->user()?->can('changeMemberRole', [$space, $member]) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([SpaceRole::Member->value, SpaceRole::Moderator->value])],
        ];
    }
}
