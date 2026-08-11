<?php

namespace App\Models;

use App\Enums\SpaceEventRsvpStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $space_event_id
 * @property int $user_id
 * @property SpaceEventRsvpStatus $status
 */
class SpaceEventRsvp extends Model
{
    protected $fillable = [
        'space_event_id',
        'user_id',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => SpaceEventRsvpStatus::class];
    }

    /** @return BelongsTo<SpaceEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(SpaceEvent::class, 'space_event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
