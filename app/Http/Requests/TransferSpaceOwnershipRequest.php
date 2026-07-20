<?php

namespace App\Http\Requests;

use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;

class TransferSpaceOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');

        return $space instanceof Space
            && $this->user()?->can('transferOwnership', $space) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
