<?php

namespace App\Privacy;

use App\Models\User;
use Generator;
use Illuminate\Support\Facades\DB;

class PersonalDataExport
{
    /**
     * Build the export structure. Large user-owned collections remain lazy so
     * the HTTP response can stream them without loading the full export.
     *
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $userId = (int) $user->getKey();
        $preferences = DB::table('notification_preferences')
            ->where('user_id', $userId)
            ->first();
        $preferenceValues = $preferences === null
            ? [
                'comment_replies' => true,
                'content_mentions' => true,
                'space_moderation' => true,
            ]
            : [
                'comment_replies' => (bool) $preferences->comment_replies,
                'content_mentions' => (bool) $preferences->content_mentions,
                'space_moderation' => (bool) $preferences->space_moderation,
            ];

        return [
            'export_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'id' => $userId,
                'name' => $user->name,
                'handle' => $user->handle,
                'headline' => $user->headline,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'bio' => $user->bio,
                'location' => $user->location,
                'website_url' => $user->website_url,
                'profile_visibility' => $user->profile_visibility->value,
                'is_discoverable' => $user->is_discoverable,
                'platform_role' => $user->platform_role->value,
                'suspended_at' => $user->suspended_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'space_memberships' => $this->spaceMemberships($userId),
            'owned_spaces' => $this->ownedSpaces($userId),
            'posts' => $this->posts($userId),
            'comments' => $this->comments($userId),
            'post_reactions' => $this->postReactions($userId),
            'saved_posts' => $this->savedPosts($userId),
            'following' => $this->following($userId),
            'followers' => $this->followers($userId),
            'safety_relationships' => $this->safetyRelationships($userId),
            'direct_messages' => $this->directMessages($userId),
            'notifications' => $this->notifications($userId),
            'space_invitation_activity' => $this->spaceInvitationActivity($userId),
            'moderation_actions' => $this->moderationActions($userId),
            'submitted_post_reports' => $this->submittedPostReports($userId),
            'submitted_comment_reports' => $this->submittedCommentReports($userId),
            'submitted_direct_message_reports' => $this->submittedDirectMessageReports($userId),
            'notification_preferences' => $preferenceValues,
            'security' => [
                'two_factor_enabled' => filled($user->two_factor_secret),
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
                'api_tokens' => $this->apiTokens($userId),
                'passkeys' => $this->passkeys($userId),
                'sessions' => $this->sessions($userId),
            ],
        ];
    }

    /** @return Generator<int, array<string, mixed>> */
    private function spaceMemberships(int $userId): Generator
    {
        $rows = DB::table('space_members')
            ->join('spaces', 'spaces.id', '=', 'space_members.space_id')
            ->where('space_members.user_id', $userId)
            ->orderBy('spaces.id')
            ->select([
                'spaces.id',
                'spaces.name',
                'spaces.slug',
                'spaces.visibility',
                'space_members.role',
                'space_members.created_at as joined_at',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield [
                'space_id' => $row->id,
                'space_name' => $row->name,
                'space_slug' => $row->slug,
                'visibility' => $row->visibility,
                'role' => $row->role,
                'joined_at' => $row->joined_at,
            ];
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function ownedSpaces(int $userId): Generator
    {
        $rows = DB::table('spaces')
            ->where('owner_id', $userId)
            ->orderBy('id')
            ->select(['id', 'name', 'slug', 'description', 'visibility', 'created_at', 'updated_at'])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function posts(int $userId): Generator
    {
        $rows = DB::table('posts')
            ->join('spaces', 'spaces.id', '=', 'posts.space_id')
            ->leftJoin('post_media', 'post_media.post_id', '=', 'posts.id')
            ->where('posts.user_id', $userId)
            ->orderBy('posts.id')
            ->select([
                'posts.id',
                'spaces.slug as space_slug',
                'posts.body',
                'posts.published_at',
                'posts.edited_at',
                'posts.hidden_at',
                'posts.created_at',
                'posts.updated_at',
                'post_media.mime_type as media_mime_type',
                'post_media.width as media_width',
                'post_media.height as media_height',
                'post_media.size_bytes as media_size_bytes',
                'post_media.alt_text as media_alt_text',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            $post = (array) $row;
            $post['media'] = $row->media_mime_type === null ? null : [
                'mime_type' => $row->media_mime_type,
                'width' => $row->media_width,
                'height' => $row->media_height,
                'size_bytes' => $row->media_size_bytes,
                'alt_text' => $row->media_alt_text,
            ];

            unset(
                $post['media_mime_type'],
                $post['media_width'],
                $post['media_height'],
                $post['media_size_bytes'],
                $post['media_alt_text'],
            );

            yield $post;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function comments(int $userId): Generator
    {
        $rows = DB::table('comments')
            ->join('posts', 'posts.id', '=', 'comments.post_id')
            ->join('spaces', 'spaces.id', '=', 'posts.space_id')
            ->where('comments.user_id', $userId)
            ->orderBy('comments.id')
            ->select([
                'comments.id',
                'comments.post_id',
                'spaces.slug as space_slug',
                'comments.body',
                'comments.published_at',
                'comments.edited_at',
                'comments.hidden_at',
                'comments.created_at',
                'comments.updated_at',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function postReactions(int $userId): Generator
    {
        yield from $this->postReferences('post_reactions', $userId, ['post_reactions.type']);
    }

    /** @return Generator<int, array<string, mixed>> */
    private function savedPosts(int $userId): Generator
    {
        yield from $this->postReferences('post_saves', $userId);
    }

    /**
     * @param  list<string>  $extraColumns
     * @return Generator<int, array<string, mixed>>
     */
    private function postReferences(string $table, int $userId, array $extraColumns = []): Generator
    {
        $rows = DB::table($table)
            ->join('posts', 'posts.id', '=', "{$table}.post_id")
            ->join('spaces', 'spaces.id', '=', 'posts.space_id')
            ->where("{$table}.user_id", $userId)
            ->orderBy("{$table}.id")
            ->select([
                "{$table}.post_id",
                'spaces.slug as space_slug',
                "{$table}.created_at",
                ...$extraColumns,
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function following(int $userId): Generator
    {
        yield from $this->follows($userId, 'follower_id', 'followed_id');
    }

    /** @return Generator<int, array<string, mixed>> */
    private function followers(int $userId): Generator
    {
        yield from $this->follows($userId, 'followed_id', 'follower_id');
    }

    /** @return Generator<int, array<string, mixed>> */
    private function follows(int $userId, string $ownerColumn, string $relatedColumn): Generator
    {
        $rows = DB::table('user_follows')
            ->join('users', 'users.id', '=', "user_follows.{$relatedColumn}")
            ->where("user_follows.{$ownerColumn}", $userId)
            ->orderBy('user_follows.id')
            ->select(['users.handle', 'user_follows.created_at'])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function safetyRelationships(int $userId): Generator
    {
        $rows = DB::table('user_relationships')
            ->join('users', 'users.id', '=', 'user_relationships.target_id')
            ->where('user_relationships.actor_id', $userId)
            ->orderBy('user_relationships.id')
            ->select(['users.handle', 'user_relationships.type', 'user_relationships.created_at'])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function directMessages(int $userId): Generator
    {
        $rows = DB::table('direct_messages')
            ->join('conversations', 'conversations.id', '=', 'direct_messages.conversation_id')
            ->join('users as user_one', 'user_one.id', '=', 'conversations.user_one_id')
            ->join('users as user_two', 'user_two.id', '=', 'conversations.user_two_id')
            ->where('direct_messages.sender_id', $userId)
            ->orderBy('direct_messages.id')
            ->select([
                'direct_messages.id',
                'direct_messages.body',
                'direct_messages.created_at',
                'conversations.user_one_id',
                'user_one.handle as user_one_handle',
                'user_two.handle as user_two_handle',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield [
                'id' => $row->id,
                'recipient_handle' => (int) $row->user_one_id === $userId
                    ? $row->user_two_handle
                    : $row->user_one_handle,
                'body' => $row->body,
                'sent_at' => $row->created_at,
            ];
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function notifications(int $userId): Generator
    {
        $rows = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->orderBy('created_at')
            ->select(['type', 'read_at', 'created_at'])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function spaceInvitationActivity(int $userId): Generator
    {
        $rows = DB::table('space_invitations')
            ->join('spaces', 'spaces.id', '=', 'space_invitations.space_id')
            ->where(function ($invitations) use ($userId): void {
                $invitations
                    ->where('space_invitations.invited_by', $userId)
                    ->orWhere('space_invitations.accepted_by', $userId);
            })
            ->orderBy('space_invitations.id')
            ->select([
                'spaces.slug as space_slug',
                'space_invitations.invited_by',
                'space_invitations.accepted_by',
                'space_invitations.role',
                'space_invitations.expires_at',
                'space_invitations.accepted_at',
                'space_invitations.revoked_at',
                'space_invitations.created_at',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield [
                'space_slug' => $row->space_slug,
                'relationship' => (int) $row->invited_by === $userId ? 'sent' : 'accepted',
                'role' => $row->role,
                'expires_at' => $row->expires_at,
                'accepted_at' => $row->accepted_at,
                'revoked_at' => $row->revoked_at,
                'created_at' => $row->created_at,
            ];
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function moderationActions(int $userId): Generator
    {
        $rows = DB::table('space_audit_logs')
            ->join('spaces', 'spaces.id', '=', 'space_audit_logs.space_id')
            ->where('space_audit_logs.actor_id', $userId)
            ->orderBy('space_audit_logs.id')
            ->select([
                'spaces.slug as space_slug',
                'space_audit_logs.action',
                'space_audit_logs.reason',
                'space_audit_logs.created_at',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function submittedPostReports(int $userId): Generator
    {
        $rows = DB::table('post_reports')
            ->join('spaces', 'spaces.id', '=', 'post_reports.space_id')
            ->where('post_reports.reporter_id', $userId)
            ->orderBy('post_reports.id')
            ->select([
                'post_reports.id',
                'post_reports.post_id',
                'spaces.slug as space_slug',
                'post_reports.reason',
                'post_reports.details',
                'post_reports.status',
                'post_reports.reviewed_at',
                'post_reports.created_at',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function submittedCommentReports(int $userId): Generator
    {
        $rows = DB::table('comment_reports')
            ->join('spaces', 'spaces.id', '=', 'comment_reports.space_id')
            ->where('comment_reports.reporter_id', $userId)
            ->orderBy('comment_reports.id')
            ->select([
                'comment_reports.id',
                'comment_reports.comment_id',
                'spaces.slug as space_slug',
                'comment_reports.reason',
                'comment_reports.details',
                'comment_reports.status',
                'comment_reports.reviewed_at',
                'comment_reports.created_at',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function submittedDirectMessageReports(int $userId): Generator
    {
        $rows = DB::table('direct_message_reports')
            ->where('reporter_id', $userId)
            ->orderBy('id')
            ->select([
                'id',
                'direct_message_id',
                'reason',
                'details',
                'message_body_snapshot',
                'message_sent_at',
                'status',
                'reviewed_at',
                'created_at',
            ])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function apiTokens(int $userId): Generator
    {
        $rows = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $userId)
            ->orderBy('id')
            ->select(['name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            ->lazy(500);

        foreach ($rows as $row) {
            yield [
                'name' => $row->name,
                'abilities' => json_decode((string) $row->abilities, true) ?? [],
                'last_used_at' => $row->last_used_at,
                'expires_at' => $row->expires_at,
                'created_at' => $row->created_at,
            ];
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function passkeys(int $userId): Generator
    {
        $rows = DB::table('passkeys')
            ->where('user_id', $userId)
            ->orderBy('id')
            ->select(['name', 'last_used_at', 'created_at'])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    private function sessions(int $userId): Generator
    {
        $rows = DB::table('sessions')
            ->where('user_id', $userId)
            ->orderBy('last_activity')
            ->select(['ip_address', 'user_agent', 'last_activity'])
            ->lazy(500);

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }
}
