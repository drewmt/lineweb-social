<?php

namespace App\Community;

use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PrivateMessaging
{
    public function start(User $sender, User $recipient, string $body): Conversation
    {
        return DB::transaction(function () use ($sender, $recipient, $body): Conversation {
            $members = User::query()
                ->whereKey([$sender->getKey(), $recipient->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (User $user): int => (int) $user->getKey());

            /** @var User $lockedSender */
            $lockedSender = $members->get((int) $sender->getKey());
            /** @var User $lockedRecipient */
            $lockedRecipient = $members->get((int) $recipient->getKey());

            Gate::forUser($lockedSender)->authorize('message', $lockedRecipient);

            $conversation = Conversation::between($lockedSender, $lockedRecipient);
            $lockedConversation = Conversation::query()
                ->whereKey($conversation)
                ->lockForUpdate()
                ->firstOrFail();

            $this->appendLocked($lockedConversation, $lockedSender, $body);

            return $lockedConversation->fresh(['userOne', 'userTwo', 'lastMessage']);
        });
    }

    public function send(User $sender, Conversation $conversation, string $body): DirectMessage
    {
        return DB::transaction(function () use ($sender, $conversation, $body): DirectMessage {
            User::query()
                ->whereKey([$conversation->user_one_id, $conversation->user_two_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lockedConversation = Conversation::query()
                ->with(['userOne', 'userTwo'])
                ->whereKey($conversation)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($sender)->authorize('send', $lockedConversation);

            return $this->appendLocked($lockedConversation, $sender, $body);
        });
    }

    public function markRead(User $viewer, Conversation $conversation): void
    {
        DB::transaction(function () use ($viewer, $conversation): void {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($viewer)->authorize('view', $lockedConversation);

            if ($lockedConversation->last_message_id === null) {
                return;
            }

            $lockedConversation->update([
                $lockedConversation->readColumnFor($viewer) => $lockedConversation->last_message_id,
            ]);
        });
    }

    /** @return list<array<string, mixed>> */
    public function inboxFor(User $viewer): array
    {
        return array_values(Conversation::query()
            ->forUser($viewer)
            ->whereNotNull('last_message_id')
            ->with([
                'userOne:id,name,handle',
                'userTwo:id,name,handle',
                'lastMessage:id,conversation_id,sender_id,body,created_at',
            ])
            ->latest('last_message_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $conversation): array => $this->conversationData($viewer, $conversation))
            ->all());
    }

    /** @return array<string, mixed> */
    public function threadFor(User $viewer, Conversation $conversation): array
    {
        Gate::forUser($viewer)->authorize('view', $conversation);
        $conversation->loadMissing(['userOne:id,name,handle', 'userTwo:id,name,handle']);
        $messages = $conversation->messages()
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();
        $other = $conversation->otherParticipant($viewer);

        return [
            'id' => $conversation->getKey(),
            'other' => $this->personData($other),
            'canSend' => Gate::forUser($viewer)->allows('send', $conversation),
            'hasUnread' => $conversation->last_message_id !== null
                && $conversation->last_message_id > $conversation->lastReadMessageIdFor($viewer),
            'historyLimited' => $conversation->messages()->count() > 50,
            'messages' => $messages->map(fn (DirectMessage $message): array => [
                'id' => $message->getKey(),
                'body' => $message->body,
                'createdAt' => $message->created_at?->toIso8601String(),
                'isOwn' => $message->sender_id === $viewer->getKey(),
            ])->all(),
        ];
    }

    public function unreadCount(User $viewer): int
    {
        return Conversation::query()
            ->forUser($viewer)
            ->whereNotNull('last_message_id')
            ->where(function (Builder $participants) use ($viewer): void {
                $participants
                    ->where(function (Builder $asFirst) use ($viewer): void {
                        $asFirst
                            ->where('user_one_id', $viewer->getKey())
                            ->where(function (Builder $unread): void {
                                $unread
                                    ->whereNull('user_one_last_read_message_id')
                                    ->orWhereColumn('user_one_last_read_message_id', '<', 'last_message_id');
                            });
                    })
                    ->orWhere(function (Builder $asSecond) use ($viewer): void {
                        $asSecond
                            ->where('user_two_id', $viewer->getKey())
                            ->where(function (Builder $unread): void {
                                $unread
                                    ->whereNull('user_two_last_read_message_id')
                                    ->orWhereColumn('user_two_last_read_message_id', '<', 'last_message_id');
                            });
                    });
            })
            ->count();
    }

    private function appendLocked(
        Conversation $conversation,
        User $sender,
        string $body,
    ): DirectMessage {
        $message = $conversation->messages()->create([
            'sender_id' => $sender->getKey(),
            'body' => $body,
        ]);

        $conversation->update([
            'last_message_id' => $message->getKey(),
            'last_message_at' => $message->created_at,
            $conversation->readColumnFor($sender) => $message->getKey(),
        ]);

        return $message;
    }

    /** @return array<string, mixed> */
    private function conversationData(User $viewer, Conversation $conversation): array
    {
        $other = $conversation->otherParticipant($viewer);

        return [
            'id' => $conversation->getKey(),
            'url' => route('messages.show', $conversation),
            'other' => $this->personData($other),
            'lastMessage' => [
                'body' => $conversation->lastMessage?->body,
                'createdAt' => $conversation->lastMessage?->created_at?->toIso8601String(),
                'isOwn' => $conversation->lastMessage?->sender_id === $viewer->getKey(),
            ],
            'unread' => $conversation->last_message_id !== null
                && $conversation->last_message_id > $conversation->lastReadMessageIdFor($viewer),
        ];
    }

    /** @return array{name: string, handle: string} */
    private function personData(User $user): array
    {
        return ['name' => $user->name, 'handle' => $user->handle];
    }
}
