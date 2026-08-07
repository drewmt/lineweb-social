<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesPostGalleryUploads;
use App\Http\Requests\Concerns\HandlesPostPoll;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
{
    use HandlesPostGalleryUploads;
    use HandlesPostPoll;

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
            'body' => ['nullable', 'string', 'max:2000'],
            ...$this->galleryUploadRules(),
            ...$this->postPollRules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.max' => 'Posts can be up to 2,000 characters.',
            ...$this->galleryUploadMessages(),
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateGalleryUploads($validator);
                $this->validatePostPoll(
                    $validator,
                    'Write something or add a poll before publishing.',
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareGalleryForValidation();
        $this->preparePostPollForValidation();
        $this->merge([
            'body' => trim((string) $this->input('body')),
        ]);
    }
}
