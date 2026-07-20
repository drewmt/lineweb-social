<?php

namespace App\Models;

use App\Enums\SpaceAuditAction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $space_id
 * @property int|null $actor_id
 * @property int|null $subject_user_id
 * @property SpaceAuditAction $action
 * @property string|null $reason
 * @property array<string, mixed>|null $context
 * @property CarbonInterface $created_at
 */
class SpaceAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'space_id',
        'actor_id',
        'subject_user_id',
        'action',
        'reason',
        'context',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => SpaceAuditAction::class,
            'context' => 'array',
        ];
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }
}
