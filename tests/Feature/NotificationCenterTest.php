<?php

namespace Tests\Feature;

use App\Enums\ReportReason;
use App\Enums\SpaceRole;
use App\Events\CommentPublished;
use App\Listeners\NotifyCommentRecipient;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\ContentMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_commenting_notifies_the_post_author_without_storing_content(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create(['name' => 'Thoughtful Member']);
        $space = Space::factory()->for($author, 'owner')->create(['name' => 'Makers Circle']);
        $space->addMember($commenter);
        $post = Post::factory()->for($space)->for($author, 'author')->create();

        $this->actingAs($commenter)
            ->post(route('posts.comments.store', $post), [
                'body' => 'A private body that should not be copied into notifications.',
            ])
            ->assertRedirect();

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $author->id)
            ->sole();

        $this->assertSame('comment_reply', $notification->type);
        $this->assertSame(
            ['actor_id', 'comment_id', 'post_id', 'space_id'],
            array_keys(collect($notification->data)->sortKeys()->all()),
        );
        $this->assertSame($commenter->id, $notification->data['actor_id']);
        $this->assertStringNotContainsString('private body', json_encode($notification->data, JSON_THROW_ON_ERROR));

        $this->actingAs($author)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->where('notificationSummary.unreadCount', 1)
                ->where('filter', 'all')
                ->has('items', 1)
                ->where('items.0.kind', 'comment_reply')
                ->where('items.0.title', 'Thoughtful Member replied to your post')
                ->where('items.0.description', 'Open the conversation in Makers Circle.')
                ->where('items.0.available', true)
                ->where('items.0.readAt', null));
    }

    public function test_self_replies_mutes_and_disabled_preferences_do_not_create_notifications(): void
    {
        $commenter = User::factory()->create();

        $selfAuthor = User::factory()->create();
        $selfSpace = Space::factory()->for($selfAuthor, 'owner')->create();
        $selfPost = Post::factory()->for($selfSpace)->for($selfAuthor, 'author')->create();
        $this->actingAs($selfAuthor)
            ->post(route('posts.comments.store', $selfPost), ['body' => 'My own follow-up.'])
            ->assertRedirect();

        $mutingAuthor = User::factory()->create();
        $mutingSpace = Space::factory()->for($mutingAuthor, 'owner')->create();
        $mutingSpace->addMember($commenter);
        $mutingPost = Post::factory()->for($mutingSpace)->for($mutingAuthor, 'author')->create();
        $mutingAuthor->outgoingRelationships()->create([
            'target_id' => $commenter->id,
            'type' => 'mute',
        ]);
        $this->actingAs($commenter)
            ->post(route('posts.comments.store', $mutingPost), ['body' => 'Muted reply.'])
            ->assertRedirect();

        $quietAuthor = User::factory()->create();
        $quietSpace = Space::factory()->for($quietAuthor, 'owner')->create();
        $quietSpace->addMember($commenter);
        $quietPost = Post::factory()->for($quietSpace)->for($quietAuthor, 'author')->create();
        $quietAuthor->notificationPreference()->create([
            'comment_replies' => false,
            'space_moderation' => true,
        ]);
        $this->actingAs($commenter)
            ->post(route('posts.comments.store', $quietPost), ['body' => 'Preference-disabled reply.'])
            ->assertRedirect();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notifications_index_supports_notification_kind_filters(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($viewer, 'owner')->create(['name' => 'Makers Circle']);
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create();

        $viewer->notify(new CommentReplyNotification($comment));
        $viewer->notify(ContentMentionNotification::forPost($post));

        $this->actingAs($viewer)
            ->get(route('notifications.index', ['kind' => 'content_mention']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter', 'all')
                ->where('kind', 'content_mention')
                ->where('meta.unreadCount', 1)
                ->has('items', 1)
                ->where('items.0.kind', 'content_mention'));

        $this->actingAs($viewer)
            ->get(route('notifications.index', ['kind' => 'comment_reply']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kind', 'comment_reply')
                ->has('items', 1)
                ->where('items.0.kind', 'comment_reply'));

        $this->actingAs($viewer)
            ->get(route('notifications.index', ['kind' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kind', 'all')
                ->has('items', 2));
    }

    public function test_direct_reply_notifies_the_parent_author_instead_of_the_post_author(): void
    {
        $postAuthor = User::factory()->create();
        $parentAuthor = User::factory()->create();
        $replyAuthor = User::factory()->create(['name' => 'Helpful Member']);
        $space = Space::factory()->for($postAuthor, 'owner')->create(['name' => 'Builders']);
        $space->addMember($parentAuthor);
        $space->addMember($replyAuthor);
        $post = Post::factory()->for($space)->for($postAuthor, 'author')->create();
        $parent = Comment::factory()->for($post)->for($parentAuthor, 'author')->create();

        $this->actingAs($replyAuthor)
            ->post(route('posts.comments.store', $post), [
                'body' => 'A direct response.',
                'parent_id' => $parent->getKey(),
            ])
            ->assertRedirect();

        $notification = DatabaseNotification::query()->sole();
        $this->assertSame($parentAuthor->getKey(), $notification->notifiable_id);
        $this->assertSame($parent->getKey(), $notification->data['reply_to_comment_id']);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $postAuthor->getKey()]);

        $this->actingAs($parentAuthor)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.title', 'Helpful Member replied to your comment')
                ->where('items.0.description', 'Open the conversation in Builders.'));
    }

    public function test_parent_deletion_before_notification_delivery_does_not_notify_the_post_author(): void
    {
        $postAuthor = User::factory()->create();
        $parentAuthor = User::factory()->create();
        $replyAuthor = User::factory()->create();
        $post = Post::factory()->for($postAuthor, 'author')->create();
        $parent = Comment::factory()->for($post)->for($parentAuthor, 'author')->create();
        $reply = Comment::factory()->for($post)->for($replyAuthor, 'author')->create([
            'parent_id' => $parent->getKey(),
        ]);
        $staleReply = Comment::query()->findOrFail($reply->getKey());

        $parent->delete();

        $this->assertNull(Comment::query()->findOrFail($reply->getKey())->parent_id);

        app(NotifyCommentRecipient::class)->handle(new CommentPublished($staleReply));

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_reports_notify_only_eligible_moderators_without_reporter_details(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);
        $moderator->notificationPreference()->create([
            'comment_replies' => true,
            'space_moderation' => false,
        ]);
        $post = Post::factory()->for($space)->for($author, 'author')->create();

        $this->actingAs($reporter)
            ->post(route('posts.reports.store', $post), [
                'reason' => ReportReason::Privacy->value,
                'details' => 'Sensitive reporter context.',
            ])
            ->assertRedirect();

        $notification = DatabaseNotification::query()->sole();
        $this->assertSame($owner->id, $notification->notifiable_id);
        $this->assertSame('space_moderation', $notification->type);
        $this->assertSame(
            ['report_id', 'report_kind', 'space_id'],
            array_keys(collect($notification->data)->sortKeys()->all()),
        );
        $this->assertSame('post', $notification->data['report_kind']);
        $payload = json_encode($notification->data, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString((string) $reporter->id, $payload);
        $this->assertStringNotContainsString('Sensitive reporter context', $payload);

        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $moderator->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $member->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $reporter->id]);
    }

    public function test_notification_actions_are_owner_scoped_and_manage_read_state(): void
    {
        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($recipient, 'owner')->create();
        $post = Post::factory()->for($space)->for($recipient, 'author')->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create();
        $recipient->notify(new CommentReplyNotification($comment));
        $first = DatabaseNotification::query()->sole();

        $this->actingAs($other)
            ->patch(route('notifications.read', $first->id))
            ->assertNotFound();

        $this->actingAs($recipient)
            ->patch(route('notifications.read', $first->id))
            ->assertRedirect();
        $this->assertNotNull($first->refresh()->read_at);

        $recipient->notify(new CommentReplyNotification($comment));
        $second = DatabaseNotification::query()->whereNull('read_at')->sole();

        $this->actingAs($recipient)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $recipient->id,
            'read_at' => null,
        ]);

        $second->markAsUnread();
        $this->actingAs($recipient)
            ->post(route('notifications.open', $second->id))
            ->assertRedirect(route('posts.show', $post).'#comment-'.$comment->id);
        $this->assertNotNull($second->refresh()->read_at);
    }

    public function test_mark_all_read_respects_the_selected_notification_kind(): void
    {
        $recipient = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($recipient, 'owner')->create();
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create();

        $recipient->notify(new CommentReplyNotification($comment));
        $recipient->notify(ContentMentionNotification::forPost($post));

        $this->actingAs($recipient)
            ->patch(route('notifications.read-all', ['kind' => 'comment_reply']))
            ->assertRedirect()
            ->assertSessionHas('status', 'All notifications marked as read.');

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $recipient->getKey(),
            'type' => 'comment_reply',
            'read_at' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->getKey(),
            'type' => 'content_mention',
            'read_at' => null,
        ]);

        $this->actingAs($recipient)
            ->get(route('notifications.index', ['kind' => 'comment_reply']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.unreadCount', 0));

        $this->actingAs($recipient)
            ->get(route('notifications.index', ['kind' => 'content_mention']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.unreadCount', 1));
    }

    public function test_reply_opening_resolves_the_page_that_currently_contains_the_comment(): void
    {
        $recipient = User::factory()->create();
        $commenter = User::factory()->create();
        $space = Space::factory()->for($recipient, 'owner')->create();
        $post = Post::factory()->for($space)->for($recipient, 'author')->create();
        $target = Comment::factory()->for($post)->for($commenter, 'author')->create();

        Comment::factory()->count(20)->for($post)->for($commenter, 'author')->create();
        $recipient->notify(new CommentReplyNotification($target));
        $notification = DatabaseNotification::query()->sole();

        $this->actingAs($recipient)
            ->post(route('notifications.open', $notification->id))
            ->assertRedirect(route('posts.show', ['post' => $post, 'page' => 2]).'#comment-'.$target->id);
    }

    public function test_stale_notifications_do_not_expose_deleted_or_inaccessible_targets(): void
    {
        $recipient = User::factory()->create();
        $commenter = User::factory()->create();
        $space = Space::factory()->for($recipient, 'owner')->create();
        $post = Post::factory()->for($space)->for($recipient, 'author')->create();
        $comment = Comment::factory()->for($post)->for($commenter, 'author')->create();
        $recipient->notify(new CommentReplyNotification($comment));
        $notification = DatabaseNotification::query()->sole();
        $comment->delete();

        $this->actingAs($recipient)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.kind', 'unavailable')
                ->where('items.0.available', false)
                ->where('items.0.title', 'Notification unavailable'));

        $this->actingAs($recipient)
            ->post(route('notifications.open', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'This notification is no longer available.');
    }
}
