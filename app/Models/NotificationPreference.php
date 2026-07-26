<?php

namespace App\Models;

use App\Enums\NotificationDigestFrequency;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property bool $comment_replies
 * @property bool $content_mentions
 * @property bool $space_moderation
 * @property NotificationDigestFrequency $email_digest_frequency
 * @property CarbonInterface|null $email_digest_cursor_at
 * @property string|null $email_digest_cursor_notification_id
 * @property-read User $user
 */
class NotificationPreference extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'comment_replies',
        'content_mentions',
        'space_moderation',
        'email_digest_frequency',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'comment_replies' => 'boolean',
            'content_mentions' => 'boolean',
            'space_moderation' => 'boolean',
            'email_digest_frequency' => NotificationDigestFrequency::class,
            'email_digest_cursor_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
