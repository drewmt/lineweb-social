<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 */
class PersonalFeed extends Model
{
    protected $fillable = ['user_id', 'name'];

    /**
     * @param  Builder<PersonalFeed>  $query
     * @return Builder<PersonalFeed>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    /** @return BelongsToMany<User, $this> */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'personal_feed_people', 'personal_feed_id', 'person_id');
    }

    /** @return BelongsToMany<Space, $this> */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'personal_feed_spaces');
    }

    /** @return BelongsToMany<Topic, $this> */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'personal_feed_topics');
    }
}
