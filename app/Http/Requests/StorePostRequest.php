<?php

namespace App\Http\Requests;

use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');

        return $space instanceof Space
            && $this->user()?->can('createPost', $space) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Write something before publishing.',
            'body.max' => 'Posts can be up to 2,000 characters.',
        ];
    }
}
