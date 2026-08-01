<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesPostGalleryUploads;
use App\Http\Requests\Concerns\HandlesPostPoll;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePostDraftRequest extends FormRequest
{
    use HandlesPostGalleryUploads;
    use HandlesPostPoll;

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
            'body' => ['nullable', 'string', 'max:2000'],
            'space' => ['required', 'string', 'max:255', Rule::exists('spaces', 'slug')],
            ...$this->galleryUploadRules(),
            'retained_media' => ['sometimes', 'array', 'max:'.(int) config('media.max_gallery_items')],
            'retained_media.*' => ['integer', 'distinct'],
            'retained_media_alts' => ['sometimes', 'array'],
            'retained_media_alts.*' => ['nullable', 'string', 'max:300'],
            'remove_image' => ['sometimes', 'boolean'],
            ...$this->postPollRules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.max' => 'Posts can be up to 2,000 characters.',
            'space.required' => 'Choose a Space for this post.',
            'space.exists' => 'Choose a Space you can currently post in.',
            ...$this->galleryUploadMessages(),
            'retained_media.*.distinct' => 'A gallery image can be retained only once.',
            'retained_media_alts.*.max' => 'Alternative text can be up to 300 characters.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $draft = $this->route('post');
                $requestedIds = $this->requestedRetainedMediaIds();
                $retained = $draft instanceof Post
                    ? $this->retainedMediaAltTexts($draft)
                    : [];

                if (! $draft instanceof Post && $requestedIds !== []) {
                    $validator->errors()->add('retained_media', 'A new draft cannot retain existing media.');
                }

                if ($draft instanceof Post
                    && $this->has('retained_media')
                    && count($requestedIds) !== count($retained)) {
                    $validator->errors()->add(
                        'retained_media',
                        'Retained gallery images must belong to this draft.',
                    );
                }

                if (collect($retained)->contains(static fn (string $alt): bool => $alt === '')) {
                    $validator->errors()->add(
                        'retained_media_alts',
                        'Describe every retained image for members using screen readers.',
                    );
                }

                $this->validateGalleryUploads($validator, count($retained));
                $this->validatePostPoll(
                    $validator,
                    'Write something or add a poll before saving this draft.',
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareGalleryForValidation();
        $this->preparePostPollForValidation();
        $retainedAlts = $this->input('retained_media_alts');

        $prepared = [
            'body' => trim((string) $this->input('body')),
            'space' => trim((string) $this->input('space')),
            'remove_image' => $this->boolean('remove_image'),
        ];

        if ($this->has('retained_media_alts')) {
            $prepared['retained_media_alts'] = is_array($retainedAlts)
                ? array_map(
                    static fn (mixed $alt): mixed => is_string($alt) ? trim($alt) : $alt,
                    $retainedAlts,
                )
                : $retainedAlts;
        }

        $this->merge($prepared);
    }

    /**
     * @return array<int, string>
     */
    public function retainedMediaAltTexts(Post $draft): array
    {
        $draft->loadMissing('mediaItems');
        $available = $draft->mediaItems->keyBy->getKey();
        $requestedIds = $this->has('retained_media')
            ? $this->requestedRetainedMediaIds()
            : ($this->boolean('remove_image')
                || ($this->hasFile('image') && ! $this->hasFile('images'))
                ? []
                : $available->keys()->all());
        $requestedAlts = $this->input('retained_media_alts', []);
        $requestedAlts = is_array($requestedAlts) ? $requestedAlts : [];
        $retained = [];

        foreach ($requestedIds as $id) {
            $media = $available->get((int) $id);

            if (! $media instanceof PostMedia) {
                continue;
            }

            $alt = $requestedAlts[(string) $media->getKey()]
                ?? $requestedAlts[$media->getKey()]
                ?? ($available->count() === 1 && $this->filled('image_alt')
                    ? $this->input('image_alt')
                    : $media->alt_text);
            $retained[$media->getKey()] = trim(is_string($alt) ? $alt : '');
        }

        return $retained;
    }

    /** @return list<int> */
    private function requestedRetainedMediaIds(): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter(
                (array) $this->input('retained_media', []),
                static fn (mixed $id): bool => is_numeric($id),
            ),
        ));
    }
}
