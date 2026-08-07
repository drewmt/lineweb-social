<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContentMentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_post_mention_creates_one_low_data_notification_and_visible_link(): void
    {
        $author = User::factory()->create(['name' => 'Andrew', 'handle' => 'andrew']);
        $mentioned = User::factory()->create(['name' => 'Maya Chen', 'handle' => 'maya-chen']);
        $space = Space::factory()->for($author, 'owner')->create(['name' => 'Makers Circle']);
        $space->addMember($mentioned);

        $this->actingAs($author)
            ->post(route('spaces.posts.store', $space), [
                'body' => 'Welcome @maya-chen — thank you again @maya-chen.',
            ])
            ->assertRedirect();

        $post = Post::query()->sole();
        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $mentioned->id)
            ->sole();

        $this->assertSame('content_mention', $notification->type);
        $this->assertSame(
            ['actor_id', 'content_id', 'content_type', 'post_id'],
            array_keys(collect($notification->data)->sortKeys()->all()),
        );
        $this->assertStringNotContainsString(
            'Welcome',
            json_encode($notification->data, JSON_THROW_ON_ERROR),
        );

        $this->actingAs($mentioned)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', $post->id)
                ->has('posts.0.mentions', 1)
                ->where('posts.0.mentions.0.handle', 'maya-chen')
                ->where('posts.0.mentions.0.url', route('people.show', $mentioned)));

        $this->actingAs($mentioned)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.kind', 'content_mention')
                ->where('items.0.title', 'Andrew mentioned you in a post')
                ->where('items.0.description', 'Open the post in Makers Circle.')
                ->where('items.0.available', true));
    }

    public function test_comment_mentions_respect_self_block_mute_and_notification_preferences(): void
    {
        $author = User::factory()->create(['handle' => 'author']);
        $mentioned = User::factory()->create(['handle' => 'mentioned']);
        $muted = User::factory()->create(['handle' => 'muted']);
        $blocked = User::factory()->create(['handle' => 'blocked']);
        $quiet = User::factory()->create(['handle' => 'quiet']);
        $space = Space::factory()->for($author, 'owner')->create();

        foreach ([$mentioned, $muted, $blocked, $quiet] as $member) {
            $space->addMember($member);
        }

        $post = Post::factory()->for($space)->for($mentioned, 'author')->create();
        $muted->outgoingRelationships()->create([
            'target_id' => $author->id,
            'type' => 'mute',
        ]);
        $blocked->outgoingRelationships()->create([
            'target_id' => $author->id,
            'type' => 'block',
        ]);
        $quiet->notificationPreference()->create([
            'content_mentions' => false,
            'comment_replies' => true,
            'space_moderation' => true,
        ]);

        $this->actingAs($author)
            ->post(route('posts.comments.store', $post), [
                'body' => '@author @mentioned @muted @blocked @quiet',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $mentioned->id,
            'type' => 'comment_reply',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $mentioned->id,
            'type' => 'content_mention',
        ]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $author->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $muted->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $blocked->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $quiet->id]);
    }

    public function test_editing_content_notifies_only_new_mentions_and_removes_stale_links(): void
    {
        $author = User::factory()->create(['handle' => 'author']);
        $first = User::factory()->create(['handle' => 'first-member']);
        $second = User::factory()->create(['handle' => 'second-member']);
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($first);
        $space->addMember($second);
        $post = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'Hello @first-member.',
        ]);

        $this->actingAs($author)
            ->patch(route('posts.update', $post), [
                'body' => 'Hello @second-member and @second-member.',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $first->id]);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $second->id,
            'type' => 'content_mention',
        ]);

        $this->actingAs($author)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('post.mentions', 1)
                ->where('post.mentions.0.handle', 'second-member'));

        $this->actingAs($author)
            ->patch(route('posts.update', $post), [
                'body' => 'Still here @second-member.',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_direct_reply_mentions_are_deduplicated_with_a_preference_aware_fallback(): void
    {
        $postAuthor = User::factory()->create();
        $parentAuthor = User::factory()->create(['handle' => 'parent-author']);
        $replyAuthor = User::factory()->create();
        $space = Space::factory()->for($postAuthor, 'owner')->create();
        $space->addMember($parentAuthor);
        $space->addMember($replyAuthor);
        $post = Post::factory()->for($space)->for($postAuthor, 'author')->create();
        $firstParent = Comment::factory()->for($post)->for($parentAuthor, 'author')->create();

        $this->actingAs($replyAuthor)
            ->post(route('posts.comments.store', $post), [
                'body' => 'Thanks @parent-author.',
                'parent_id' => $firstParent->getKey(),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $parentAuthor->getKey(),
            'type' => 'comment_reply',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $parentAuthor->getKey(),
            'type' => 'content_mention',
        ]);

        DatabaseNotification::query()->delete();
        $parentAuthor->notificationPreference()->create([
            'comment_replies' => false,
            'content_mentions' => true,
            'space_moderation' => true,
        ]);
        $secondParent = Comment::factory()->for($post)->for($parentAuthor, 'author')->create();

        $this->actingAs($replyAuthor)
            ->post(route('posts.comments.store', $post), [
                'body' => 'A mention fallback for @parent-author.',
                'parent_id' => $secondParent->getKey(),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $parentAuthor->getKey(),
            'type' => 'content_mention',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $parentAuthor->getKey(),
            'type' => 'comment_reply',
        ]);
    }

    public function test_a_mention_notification_becomes_unavailable_after_access_is_revoked(): void
    {
        $author = User::factory()->create(['handle' => 'author']);
        $mentioned = User::factory()->create(['handle' => 'mentioned']);
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($mentioned);

        $this->actingAs($author)
            ->post(route('spaces.posts.store', $space), [
                'body' => 'Hello @mentioned.',
            ])
            ->assertRedirect();

        $notification = DatabaseNotification::query()->sole();
        $author->outgoingRelationships()->create([
            'target_id' => $mentioned->id,
            'type' => 'block',
        ]);

        $this->actingAs($mentioned)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.kind', 'unavailable')
                ->where('items.0.available', false));

        $this->actingAs($mentioned)
            ->post(route('notifications.open', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('status', 'This notification is no longer available.');
    }
}
