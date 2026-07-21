<?php

namespace App\Http\Requests;

use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
            'image' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.(int) config('media.max_upload_kilobytes'),
                'dimensions:max_width=6000,max_height=6000',
            ],
            'image_alt' => [
                Rule::requiredIf(fn (): bool => $this->hasFile('image')),
                'nullable',
                'string',
                'max:300',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Write something before publishing.',
            'body.max' => 'Posts can be up to 2,000 characters.',
            'image.mimetypes' => 'Choose a JPEG, PNG, or WebP image.',
            'image.max' => 'Images can be up to 8 MiB.',
            'image.dimensions' => 'Images can be up to 6,000 pixels wide or tall.',
            'image_alt.required' => 'Describe the image for members using screen readers.',
            'image_alt.max' => 'Alternative text can be up to 300 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body')),
            'image_alt' => $this->filled('image_alt')
                ? trim((string) $this->input('image_alt'))
                : null,
        ]);
    }
}
