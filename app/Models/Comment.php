<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property string $body
 * @property Carbon $published_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $hidden_at
 * @property int|null $hidden_by
 * @property string|null $moderation_note
 * @property-read Post $post
 * @property-read User $author
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'body',
        'published_at',
        'edited_at',
        'hidden_at',
        'hidden_by',
        'moderation_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'edited_at' => 'immutable_datetime',
            'hidden_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    /** @return HasMany<CommentReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(CommentReport::class);
    }
}
