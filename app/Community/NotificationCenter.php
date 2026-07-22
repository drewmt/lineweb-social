<?php

namespace App\Community;

use App\Enums\NotificationType;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

final class NotificationCenter
{
    private const PER_PAGE = 20;

    public function __construct(private readonly PostConversation $conversations) {}

    /**
     * @return array{
     *     id: string,
     *     kind: string,
     *     title: string,
     *     description: string,
     *     created_at: string,
     *     read_at: string|null,
     *     available: bool,
     *     target: array{
     *         type: string,
     *         post_id?: string,
     *         comment_id?: string,
     *         space_slug?: string,
     *     }|null
     * }
     */
    public function apiItem(User $viewer, DatabaseNotification $notification): array
    {
        $resolved = $this->resolve($viewer, $notification);

        return [
            'id' => (string) $notification->getKey(),
            'kind' => $resolved['kind'],
            'title' => $resolved['title'],
            'description' => $resolved['description'],
            'created_at' => $notification->created_at->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
            'available' => $resolved['destination'] !== null,
            'target' => $this->notificationApiTarget($viewer, $notification, $resolved),
        ];
    }

    /**
     * @return array{
     *     items: list<array{id: string, kind: string, title: string, description: string, createdAt: string, readAt: string|null, available: bool}>,
     *     meta: array{currentPage: int, lastPage: int, perPage: int, total: int},
     *     links: array{previous: string|null, next: string|null}
     * }
     */
    public function for(User $viewer, string $filter): array
    {
        $notifications = DatabaseNotification::query()
            ->where('notifiable_type', $viewer->getMorphClass())
            ->where('notifiable_id', $viewer->getKey())
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
        $items = [];

        foreach ($notifications->items() as $notification) {
            $resolved = $this->resolve($viewer, $notification);
            $items[] = [
                'id' => $notification->id,
                'kind' => $resolved['kind'],
                'title' => $resolved['title'],
                'description' => $resolved['description'],
                'createdAt' => $notification->created_at->toIso8601String(),
                'readAt' => $notification->read_at?->toIso8601String(),
                'available' => $resolved['destination'] !== null,
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'currentPage' => $notifications->currentPage(),
                'lastPage' => $notifications->lastPage(),
                'perPage' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'links' => [
                'previous' => $notifications->previousPageUrl(),
                'next' => $notifications->nextPageUrl(),
            ],
        ];
    }

    public function findFor(User $viewer, string $id): DatabaseNotification
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $viewer->getMorphClass())
            ->where('notifiable_id', $viewer->getKey())
            ->findOrFail($id);
    }

    public function destination(User $viewer, DatabaseNotification $notification): ?string
    {
        return $this->resolve($viewer, $notification)['destination'];
    }

    /** @return array{kind: string, title: string, description: string, destination: string|null} */
    private function resolve(User $viewer, DatabaseNotification $notification): array
    {
        return match (NotificationType::tryFrom($notification->type)) {
            NotificationType::CommentReply => $this->resolveCommentReply($viewer, $notification),
            NotificationType::SpaceModeration => $this->resolveSpaceModeration($viewer, $notification),
            default => $this->unavailable(),
        };
    }

    /** @return array{kind: string, title: string, description: string, destination: string|null} */
    private function resolveCommentReply(User $viewer, DatabaseNotification $notification): array
    {
        $commentId = $this->integer($notification, 'comment_id');

        if ($commentId === null) {
            return $this->unavailable();
        }

        $comment = Comment::query()
            ->with(['author', 'post.author', 'post.space'])
            ->find($commentId);

        if (! $comment instanceof Comment
            || Gate::forUser($viewer)->denies('view', $comment)) {
            return $this->unavailable();
        }

        $destination = $this->conversations->urlForComment($viewer, $comment);

        if ($destination === null) {
            return $this->unavailable();
        }

        $actorVisible = User::query()
            ->visibleTo($viewer)
            ->whereKey($comment->user_id)
            ->exists();

        return [
            'kind' => NotificationType::CommentReply->value,
            'title' => $actorVisible
                ? $comment->author->name.' replied to your post'
                : 'A member replied to your post',
            'description' => 'Open the conversation in '.$comment->post->space->name.'.',
            'destination' => $destination,
        ];
    }

    /** @return array{kind: string, title: string, description: string, destination: string|null} */
    private function resolveSpaceModeration(User $viewer, DatabaseNotification $notification): array
    {
        $spaceId = $this->integer($notification, 'space_id');
        $reportId = $this->integer($notification, 'report_id');
        $reportKind = $notification->data['report_kind'] ?? null;

        if ($spaceId === null || $reportId === null || ! in_array($reportKind, ['post', 'comment'], true)) {
            return $this->unavailable();
        }

        $space = Space::query()->find($spaceId);

        if (! $space instanceof Space
            || Gate::forUser($viewer)->denies('moderate', $space)) {
            return $this->unavailable();
        }

        $reportExists = $reportKind === 'post'
            ? PostReport::query()->whereKey($reportId)->whereBelongsTo($space)->exists()
            : CommentReport::query()->whereKey($reportId)->whereBelongsTo($space)->exists();

        if (! $reportExists) {
            return $this->unavailable();
        }

        return [
            'kind' => NotificationType::SpaceModeration->value,
            'title' => 'New '.$reportKind.' report',
            'description' => 'Review the moderation queue in '.$space->name.'.',
            'destination' => route('spaces.moderation.index', $space),
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     post_id?: string,
     *     comment_id?: string,
     *     space_slug?: string,
     * }|null
     */
    private function notificationApiTarget(
        User $viewer,
        DatabaseNotification $notification,
        array $resolved,
    ): ?array {
        return match (NotificationType::tryFrom($notification->type)) {
            NotificationType::CommentReply => $this->notificationApiTargetForCommentReply($notification, $resolved),
            NotificationType::SpaceModeration => $this->notificationApiTargetForSpaceModeration(
                $viewer,
                $notification,
                $resolved,
            ),
            default => null,
        };
    }

    /**
     * @return array{type: string, post_id: string, comment_id: string}|null
     */
    private function notificationApiTargetForCommentReply(
        DatabaseNotification $notification,
        array $resolved,
    ): ?array {
        if ($resolved['destination'] === null) {
            return null;
        }

        $commentId = $this->integer($notification, 'comment_id');

        if ($commentId === null) {
            return null;
        }

        $comment = Comment::query()
            ->with(['post'])
            ->find($commentId);

        return $comment instanceof Comment
            ? [
                'type' => 'post',
                'post_id' => (string) $comment->post_id,
                'comment_id' => (string) $comment->getKey(),
            ]
            : null;
    }

    /**
     * @return array{type: string, space_slug: string}|null
     */
    private function notificationApiTargetForSpaceModeration(
        User $viewer,
        DatabaseNotification $notification,
        array $resolved,
    ): ?array {
        if ($resolved['destination'] === null) {
            return null;
        }

        $spaceId = $this->integer($notification, 'space_id');
        $reportId = $this->integer($notification, 'report_id');
        $reportKind = $notification->data['report_kind'] ?? null;

        if ($spaceId === null || $reportId === null || ! in_array($reportKind, ['post', 'comment'], true)) {
            return null;
        }

        $space = Space::query()->find($spaceId);

        if (! $space instanceof Space
            || Gate::forUser($viewer)->denies('moderate', $space)) {
            return null;
        }

        $reportExists = $reportKind === 'post'
            ? PostReport::query()->whereKey($reportId)->whereBelongsTo($space)->exists()
            : CommentReport::query()->whereKey($reportId)->whereBelongsTo($space)->exists();

        if (! $reportExists) {
            return null;
        }

        return [
            'type' => 'space_moderation',
            'space_slug' => $space->slug,
        ];
    }

    private function integer(DatabaseNotification $notification, string $key): ?int
    {
        $value = $notification->data[$key] ?? null;

        return is_int($value) && $value > 0 ? $value : null;
    }

    /** @return array{kind: string, title: string, description: string, destination: null} */
    private function unavailable(): array
    {
        return [
            'kind' => 'unavailable',
            'title' => 'Notification unavailable',
            'description' => 'The content was removed or you no longer have access.',
            'destination' => null,
        ];
    }
}
