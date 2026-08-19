<?php

namespace App\Http\Requests;

use App\Models\Space;
use App\Models\Story;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');

        return $space instanceof Space
            && $this->user()?->can('createPost', $space) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:280'],
            'background' => ['required', 'string', Rule::in(Story::BACKGROUNDS)],
            'image' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:8192',
            ],
            'alt_text' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->string('body')->trim()->isEmpty() && ! $this->hasFile('image')) {
                    $validator->errors()->add('body', 'Add a short message or an image to your Story.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.max' => 'Stories can be up to 280 characters.',
            'image.max' => 'Story images can be up to 8 MB.',
            'image.mimetypes' => 'Use a JPEG, PNG, or WebP image.',
            'alt_text.max' => 'Image descriptions can be up to 300 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body')),
            'alt_text' => trim((string) $this->input('alt_text')),
        ]);
    }
}
