<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;

class StorePostVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $draft = $this->route('post');

        return $draft instanceof Post
            && $this->user()->can('manageDraft', $draft) === true
            && $this->user()->can('createPost', $draft->space) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $maxKilobytes = min(64 * 1024, max(1, (int) config('media.video.max_input_kilobytes', 64 * 1024)));

        return [
            'video' => ['required', 'file', 'max:'.$maxKilobytes],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        if (is_string($description)) {
            $this->merge(['description' => trim($description)]);
        }
    }
}
