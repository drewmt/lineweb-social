<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $direct_message_id
 * @property int|null $reporter_id
 * @property int|null $reported_user_id
 * @property ReportReason $reason
 * @property string|null $details
 * @property string $message_body_snapshot
 * @property Carbon|null $message_sent_at
 * @property ReportStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewer_note
 * @property Carbon $created_at
 * @property-read DirectMessage|null $message
 * @property-read User|null $reporter
 * @property-read User|null $reportedUser
 * @property-read User|null $reviewer
 */
class DirectMessageReport extends Model
{
    public const RETENTION_DAYS = 180;

    protected $fillable = [
        'direct_message_id',
        'reporter_id',
        'reported_user_id',
        'reason',
        'details',
        'message_body_snapshot',
        'message_sent_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'message_sent_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<DirectMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(DirectMessage::class, 'direct_message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
