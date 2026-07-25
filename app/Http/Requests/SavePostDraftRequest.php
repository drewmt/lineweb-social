<?php

namespace App\Http\Requests;

use App\Models\Post;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class SavePostDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $draft = $this->route('post');
        $space = Space::query()
            ->where('slug', trim((string) $this->input('space')))
            ->first();

        if ($user === null
            || ! $space instanceof Space
            || $user->can('createPost', $space) !== true) {
            return false;
        }

        return ! $draft instanceof Post
            || $user->can('manageDraft', $draft) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'space' => ['required', 'string', 'max:255', Rule::exists('spaces', 'slug')],
            'image' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.(int) config('media.max_upload_kilobytes'),
                'dimensions:max_width=6000,max_height=6000',
            ],
            'image_alt' => [
                Rule::requiredIf(fn (): bool => $this->hasFile('image') || $this->retainsExistingImage()),
                'nullable',
                'string',
                'max:300',
            ],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Write something before saving this draft.',
            'body.max' => 'Posts can be up to 2,000 characters.',
            'space.required' => 'Choose a Space for this post.',
            'space.exists' => 'Choose a Space you can currently post in.',
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
            'space' => trim((string) $this->input('space')),
            'image_alt' => $this->filled('image_alt')
                ? trim((string) $this->input('image_alt'))
                : null,
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }

    private function retainsExistingImage(): bool
    {
        $draft = $this->route('post');

        return $draft instanceof Post
            && ! $this->boolean('remove_image')
            && ! $this->file('image') instanceof UploadedFile
            && $draft->media()->exists();
    }
}
