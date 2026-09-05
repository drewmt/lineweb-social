<?php

namespace App\Http\Requests;

use App\Community\CommunitySearch;
use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:all,posts,spaces,people,topics'],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.CommunitySearch::MAX_PAGE],
        ];
    }
}
