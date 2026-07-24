<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property bool $comment_replies
 * @property bool $content_mentions
 * @property bool $space_moderation
 * @property-read User $user
 */
class NotificationPreference extends Model
{
    protected $fillable = [
        'comment_replies',
        'content_mentions',
        'space_moderation',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'comment_replies' => 'boolean',
            'content_mentions' => 'boolean',
            'space_moderation' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
