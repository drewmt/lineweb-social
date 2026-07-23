<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $follower_id
 * @property int $followed_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $follower
 * @property-read User $followed
 */
#[Fillable(['follower_id', 'followed_id'])]
class UserFollow extends Model
{
    /** @return BelongsTo<User, $this> */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /** @return BelongsTo<User, $this> */
    public function followed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_id');
    }
}
