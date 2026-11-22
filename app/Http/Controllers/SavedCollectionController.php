<?php

namespace App\Http\Controllers;

use App\Community\SavedCollections;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedCollectionController extends Controller
{
    public function store(Request $request, SavedCollections $collections): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();
        $collection = $collections->create($member, $this->name($request));

        return to_route('saved.collections.show', $collection)->with('status', 'Collection created.');
    }

    public function update(Request $request, string $collection, SavedCollections $collections): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();
        $folder = $collections->find($member, $collection);
        $folder->update(['name' => $this->name($request)]);

        return to_route('saved.collections.show', $folder)->with('status', 'Collection renamed.');
    }

    public function destroy(Request $request, string $collection, SavedCollections $collections): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();
        $collections->find($member, $collection)->delete();

        return to_route('saved.index')->with('status', 'Collection deleted. Its posts are still saved.');
    }

    public function addPost(Request $request, string $collection, string $post, SavedCollections $collections): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();
        $collections->setMembership($member, $collection, $post, true);

        return back()->with('status', 'Post added to collection.');
    }

    public function removePost(Request $request, string $collection, string $post, SavedCollections $collections): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();
        $collections->setMembership($member, $collection, $post, false);

        return back()->with('status', 'Post removed from collection.');
    }

    private function name(Request $request): string
    {
        return $request->validate(['name' => ['required', 'string', 'max:80']])['name'];
    }
}
