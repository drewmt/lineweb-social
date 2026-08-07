<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property string $question
 * @property int|null $closes_after_days
 * @property Carbon|null $closes_at
 * @property-read Post $post
 */
class PostPoll extends Model
{
    protected $fillable = [
        'question',
        'closes_after_days',
        'closes_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'closes_after_days' => 'integer',
            'closes_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return HasMany<PostPollOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(PostPollOption::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /** @return HasMany<PostPollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(PostPollVote::class);
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }
}
