<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $post_poll_id
 * @property int $position
 * @property string $label
 * @property-read PostPoll $poll
 */
class PostPollOption extends Model
{
    protected $fillable = [
        'position',
        'label',
    ];

    /** @return BelongsTo<PostPoll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(PostPoll::class, 'post_poll_id');
    }

    /** @return HasMany<PostPollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(PostPollVote::class);
    }
}
