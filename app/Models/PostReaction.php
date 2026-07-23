<?php

namespace App\Models;

use App\Enums\PostReactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $post_id
 * @property int $user_id
 * @property PostReactionType $type
 * @property-read Post $post
 */
class PostReaction extends Model
{
    protected $fillable = [
        'user_id',
        'post_id',
        'type',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PostReactionType::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
