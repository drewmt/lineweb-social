<?php

namespace App\Models;

use App\Enums\SpaceRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $space_id
 * @property int|null $invited_by
 * @property string $email
 * @property SpaceRole $role
 * @property string $token_hash
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $accepted_at
 * @property int|null $accepted_by
 * @property CarbonInterface|null $revoked_at
 */
class SpaceInvitation extends Model
{
    protected $fillable = [
        'space_id',
        'invited_by',
        'email',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
        'accepted_by',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => SpaceRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
