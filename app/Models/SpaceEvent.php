<?php

namespace App\Models;

use Database\Factories\SpaceEventFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $space_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $description
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $timezone
 * @property string|null $venue
 * @property string|null $online_url
 * @property int|null $capacity
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property-read Space $space
 * @property-read User|null $creator
 * @property-read Collection<int, SpaceEventRsvp> $rsvps
 * @property-read int $going_count
 * @property-read int $interested_count
 */
class SpaceEvent extends Model
{
    /** @use HasFactory<SpaceEventFactory> */
    use HasFactory;

    protected $fillable = [
        'space_id',
        'created_by',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'timezone',
        'venue',
        'online_url',
        'capacity',
        'cancelled_at',
        'cancelled_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'capacity' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<SpaceEventRsvp, $this> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(SpaceEventRsvp::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function hasStarted(): bool
    {
        return ! $this->starts_at->isFuture();
    }
}
