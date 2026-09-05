<?php

namespace App\Http\Controllers;

use App\Community\CommunitySearch;
use App\Http\Requests\SearchRequest;
use App\Models\User;
use Illuminate\Pagination\Paginator;
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
        /** @var 'all'|'posts'|'spaces'|'people'|'topics' $type */
        $type = $request->string('type')->toString() ?: 'all';
        $page = $type === 'all' ? 1 : max(1, $request->integer('page', 1));
        $results = $search->search($viewer, $query, $type, $page);
        $pagination = null;

        if ($type !== 'all' && mb_strlen($query) >= CommunitySearch::MINIMUM_QUERY_LENGTH) {
            $paginator = new Paginator($results[$type], CommunitySearch::RESULT_LIMIT, $page, [
                'path' => route('search'),
                'query' => ['q' => $query, 'type' => $type],
            ]);
            $results[$type] = $paginator->items();
            $pagination = [
                'currentPage' => $page,
                'previousUrl' => $paginator->previousPageUrl(),
                'nextUrl' => $page < CommunitySearch::MAX_PAGE ? $paginator->nextPageUrl() : null,
                'limitReached' => $page === CommunitySearch::MAX_PAGE && $paginator->hasMorePages(),
            ];
        }

        return Inertia::render('search/index', [
            'query' => $query,
            'type' => $type,
            'minimumQueryLength' => CommunitySearch::MINIMUM_QUERY_LENGTH,
            'results' => $results,
            'pagination' => $pagination,
        ]);
    }
}
