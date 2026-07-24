<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $user_one_id
 * @property int $user_two_id
 * @property int|null $user_one_last_read_message_id
 * @property int|null $user_two_last_read_message_id
 * @property int|null $last_message_id
 * @property Carbon|null $last_message_at
 * @property-read User $userOne
 * @property-read User $userTwo
 * @property-read DirectMessage|null $lastMessage
 */
class Conversation extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'user_one_last_read_message_id',
        'user_two_last_read_message_id',
        'last_message_id',
        'last_message_at',
    ];

    public static function between(User $first, User $second): self
    {
        [$userOneId, $userTwoId] = self::orderedIds($first, $second);

        return self::query()->firstOrCreate([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ]);
    }

    /** @return array{int, int} */
    public static function orderedIds(User $first, User $second): array
    {
        $firstId = (int) $first->getKey();
        $secondId = (int) $second->getKey();

        if ($firstId === $secondId) {
            throw new LogicException('A direct conversation requires two different members.');
        }

        return $firstId < $secondId
            ? [$firstId, $secondId]
            : [$secondId, $firstId];
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $participants) use ($user): void {
            $participants
                ->where('user_one_id', $user->getKey())
                ->orWhere('user_two_id', $user->getKey());
        });
    }

    public function includes(User $user): bool
    {
        return $this->user_one_id === $user->getKey()
            || $this->user_two_id === $user->getKey();
    }

    public function otherParticipant(User $viewer): User
    {
        if (! $this->includes($viewer)) {
            throw new LogicException('The viewer is not part of this conversation.');
        }

        return $this->user_one_id === $viewer->getKey()
            ? $this->userTwo
            : $this->userOne;
    }

    public function lastReadMessageIdFor(User $viewer): int
    {
        if (! $this->includes($viewer)) {
            throw new LogicException('The viewer is not part of this conversation.');
        }

        return (int) ($this->user_one_id === $viewer->getKey()
            ? $this->user_one_last_read_message_id
            : $this->user_two_last_read_message_id);
    }

    public function readColumnFor(User $viewer): string
    {
        if (! $this->includes($viewer)) {
            throw new LogicException('The viewer is not part of this conversation.');
        }

        return $this->user_one_id === $viewer->getKey()
            ? 'user_one_last_read_message_id'
            : 'user_two_last_read_message_id';
    }

    /** @return BelongsTo<User, $this> */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /** @return BelongsTo<User, $this> */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /** @return HasMany<DirectMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class);
    }

    /** @return BelongsTo<DirectMessage, $this> */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(DirectMessage::class, 'last_message_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_message_at' => 'immutable_datetime'];
    }
}
