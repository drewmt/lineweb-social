<?php

namespace App\Http\Controllers;

use App\Community\CommunitySearch;
use App\Http\Requests\SearchRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, CommunitySearch $search): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $value = $request->validated('q');
        $query = Str::squish(is_string($value) ? $value : '');

        return Inertia::render('search/index', [
            'query' => $query,
            'minimumQueryLength' => CommunitySearch::MINIMUM_QUERY_LENGTH,
            'results' => $search->search($viewer, $query),
        ]);
    }
}
