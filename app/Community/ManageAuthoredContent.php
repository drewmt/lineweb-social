<?php

namespace App\Community;

use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ManageAuthoredContent
{
    public function updatePost(User $author, Post $post, string $body): bool
    {
        return DB::transaction(function () use ($author, $post, $body): bool {
            $lockedPost = Post::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($author)->authorize('update', $lockedPost);
            $this->ensurePostIsNotUnderReview($lockedPost);

            if ($lockedPost->body === $body) {
                return false;
            }

            return $lockedPost->update([
                'body' => $body,
                'edited_at' => now(),
            ]);
        });
    }

    public function deletePost(User $author, Post $post): void
    {
        DB::transaction(function () use ($author, $post): void {
            $lockedPost = Post::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($author)->authorize('delete', $lockedPost);
            $this->ensurePostIsNotUnderReview($lockedPost);
            $lockedPost->delete();
        });
    }

    public function updateComment(User $author, Comment $comment, string $body): bool
    {
        return DB::transaction(function () use ($author, $comment, $body): bool {
            $lockedComment = Comment::query()
                ->with('post')
                ->whereKey($comment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($author)->authorize('update', $lockedComment);
            $this->ensureCommentIsNotUnderReview($lockedComment);

            if ($lockedComment->body === $body) {
                return false;
            }

            return $lockedComment->update([
                'body' => $body,
                'edited_at' => now(),
            ]);
        });
    }

    public function deleteComment(User $author, Comment $comment): void
    {
        DB::transaction(function () use ($author, $comment): void {
            $lockedComment = Comment::query()
                ->with('post')
                ->whereKey($comment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($author)->authorize('delete', $lockedComment);
            $this->ensureCommentIsNotUnderReview($lockedComment);
            $lockedComment->delete();
        });
    }

    private function ensurePostIsNotUnderReview(Post $post): void
    {
        if ($post->reports()->whereIn('status', $this->activeStatuses())->exists()) {
            $this->throwUnderReview();
        }
    }

    private function ensureCommentIsNotUnderReview(Comment $comment): void
    {
        if ($comment->reports()->whereIn('status', $this->activeStatuses())->exists()) {
            $this->throwUnderReview();
        }
    }

    /** @return list<string> */
    private function activeStatuses(): array
    {
        return [
            ReportStatus::Open->value,
            ReportStatus::Reviewing->value,
        ];
    }

    private function throwUnderReview(): never
    {
        throw ValidationException::withMessages([
            'content' => 'This content cannot be changed while a moderation review is active.',
        ]);
    }
}
