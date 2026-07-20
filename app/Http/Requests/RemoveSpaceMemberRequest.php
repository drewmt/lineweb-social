<?php

namespace App\Http\Requests;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RemoveSpaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');
        $member = $this->route('member');

        return $space instanceof Space
            && $member instanceof User
            && $this->user()?->can('removeMember', [$space, $member]) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }
}
