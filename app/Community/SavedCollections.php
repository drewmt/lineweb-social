<?php

namespace App\Community;

use App\Models\SavedCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SavedCollections
{
    public const int LIMIT = 100;

    public function __construct(private readonly VisiblePostQuery $visiblePosts) {}

    /** @return Builder<SavedCollection> */
    public function ownedBy(User $member): Builder
    {
        return SavedCollection::query()->where('user_id', $member->getKey());
    }

    public function find(User $member, string $collection): SavedCollection
    {
        return $this->ownedBy($member)->findOrFail($this->id($collection));
    }

    public function create(User $member, string $name): SavedCollection
    {
        return DB::transaction(function () use ($member, $name): SavedCollection {
            User::query()->lockForUpdate()->findOrFail($member->getKey());

            if ($this->ownedBy($member)->count() >= self::LIMIT) {
                throw ValidationException::withMessages([
                    'name' => 'You can create up to '.self::LIMIT.' collections.',
                ]);
            }

            return SavedCollection::query()->create([
                'name' => $name,
                'user_id' => $member->getKey(),
            ]);
        }, attempts: 3);
    }

    /**
     * @param  list<int>  $postIds
     * @return array<int, list<int>>
     */
    public function memberships(User $member, array $postIds): array
    {
        $memberships = [];

        foreach (DB::table('saved_collection_posts')
            ->where('user_id', $member->getKey())
            ->whereIntegerInRaw('post_id', $postIds)
            ->orderBy('collection_id')
            ->get(['collection_id', 'post_id']) as $row) {
            $memberships[(int) $row->post_id][] = (int) $row->collection_id;
        }

        return $memberships;
    }

    public function setMembership(User $member, string $collection, string $post, bool $assigned): void
    {
        $postId = $this->id($post);
        $collectionId = $this->id($collection);

        DB::transaction(function () use ($member, $collectionId, $postId, $assigned): void {
            $this->ownedBy($member)->lockForUpdate()->findOrFail($collectionId);
            $bookmark = DB::table('post_saves')
                ->where('user_id', $member->getKey())
                ->where('post_id', $postId)
                ->lockForUpdate()
                ->first(['id']);

            abort_unless($bookmark !== null && $this->visiblePosts->forSearch($member)->whereKey($postId)->exists(), 404);

            $membership = [
                'collection_id' => $collectionId,
                'user_id' => $member->getKey(),
                'post_id' => $postId,
            ];
            $table = DB::table('saved_collection_posts');

            if ($assigned) {
                $table->insertOrIgnore($membership + ['created_at' => now()]);
            } else {
                $table->where($membership)->delete();
            }
        }, attempts: 3);
    }

    private function id(string $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return $id;
    }
}
