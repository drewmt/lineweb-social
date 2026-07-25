<?php

namespace App\Models;

use App\Enums\PlatformAppealStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $suspension_reference
 * @property Carbon $suspension_started_at
 * @property PlatformAppealStatus $status
 * @property string $statement
 * @property string|null $decision_message
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read User|null $reviewer
 */
class PlatformAppeal extends Model
{
    protected $fillable = [
        'user_id',
        'suspension_reference',
        'suspension_started_at',
        'status',
        'statement',
        'decision_message',
        'reviewed_by',
        'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'suspension_started_at' => 'immutable_datetime',
            'status' => PlatformAppealStatus::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
