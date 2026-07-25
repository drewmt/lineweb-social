<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $space_id
 * @property int $post_id
 * @property int|null $highlighted_by
 * @property Carbon $created_at
 * @property-read Space $space
 * @property-read Post $post
 * @property-read User|null $highlightedBy
 */
class SpacePostHighlight extends Model
{
    protected $fillable = [
        'space_id',
        'post_id',
        'highlighted_by',
    ];

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function highlightedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'highlighted_by');
    }
}
