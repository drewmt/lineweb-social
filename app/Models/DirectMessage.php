<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property-read Conversation $conversation
 * @property-read User $sender
 * @property-read Collection<int, DirectMessageReport> $reports
 */
class DirectMessage extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'body'];

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return HasMany<DirectMessageReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(DirectMessageReport::class);
    }
}
