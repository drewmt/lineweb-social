<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $space_id
 * @property int|null $created_by
 * @property string|null $label
 * @property string $token_hash
 * @property int $max_uses
 * @property int $uses_count
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $revoked_at
 */
class SpaceInviteLink extends Model
{
    protected $fillable = [
        'space_id',
        'created_by',
        'label',
        'token_hash',
        'max_uses',
        'uses_count',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
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

    public function isAvailable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->uses_count < $this->max_uses;
    }

    public function remainingUses(): int
    {
        return max(0, $this->max_uses - $this->uses_count);
    }
}
