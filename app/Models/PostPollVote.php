<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_poll_id
 * @property int $post_poll_option_id
 * @property int $user_id
 */
class PostPollVote extends Model
{
    protected $fillable = [
        'post_poll_id',
        'post_poll_option_id',
        'user_id',
    ];

    /** @return BelongsTo<PostPoll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(PostPoll::class, 'post_poll_id');
    }

    /** @return BelongsTo<PostPollOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(PostPollOption::class, 'post_poll_option_id');
    }

    /** @return BelongsTo<User, $this> */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
