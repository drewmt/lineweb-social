<?php

namespace App\Models;

use App\Enums\UserRelationshipType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $actor_id
 * @property int $target_id
 * @property UserRelationshipType $type
 * @property Carbon|null $created_at
 * @property-read User $actor
 * @property-read User $target
 */
class UserRelationship extends Model
{
    protected $fillable = ['actor_id', 'target_id', 'type'];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => UserRelationshipType::class];
    }
}
