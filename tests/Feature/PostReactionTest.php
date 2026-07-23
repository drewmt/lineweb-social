<?php

namespace Tests\Feature;

use App\Enums\PostReactionType;
use App\Enums\UserRelationshipType;
use App\Events\PostReactionChanged;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_post_reaction_is_idempotent_and_can_change_type(): void
    {
        Event::fake([PostReactionChanged::class]);

        $viewer = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($viewer)
            ->put(route('posts.reactions.store', $post), [
                'type' => PostReactionType::Like->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Reaction added.');

        $this->actingAs($viewer)
            ->put(route('posts.reactions.store', $post), [
                'type' => PostReactionType::Like->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Reaction unchanged.');

        $this->actingAs($viewer)
            ->put(route('posts.reactions.store', $post), [
                'type' => PostReactionType::Insightful->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Reaction updated.');

        $this->assertDatabaseCount('post_reactions', 1);
        $this->assertDatabaseHas('post_reactions', [
            'post_id' => $post->getKey(),
            'user_id' => $viewer->getKey(),
            'type' => PostReactionType::Insightful->value,
        ]);
        Event::assertDispatchedTimes(PostReactionChanged::class, 2);
        Event::assertDispatched(
            PostReactionChanged::class,
            fn (PostReactionChanged $event): bool => $event->post->is($post)
                && $event->user->is($viewer)
                && $event->previousType === PostReactionType::Like
                && $event->type === PostReactionType::Insightful,
        );
    }

    public function test_reaction_type_is_allowlisted_and_visibility_is_enforced(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $public = Space::factory()->create();
        $private = Space::factory()->private()->create();
        $visible = Post::factory()->for($public)->for($author, 'author')->create();
        $draft = Post::factory()->for($public)->for($author, 'author')->create([
            'published_at' => null,
        ]);
        $hidden = Post::factory()->for($public)->for($author, 'author')->create([
            'hidden_at' => now(),
        ]);
        $privatePost = Post::factory()->for($private)->for($author, 'author')->create();

        $this->actingAs($viewer)
            ->put(route('posts.reactions.store', $visible), ['type' => 'custom'])
            ->assertSessionHasErrors('type');

        foreach ([$draft, $hidden, $privatePost] as $post) {
            $this->actingAs($viewer)
                ->put(route('posts.reactions.store', $post), [
                    'type' => PostReactionType::Celebrate->value,
                ])
                ->assertForbidden();
        }

        $viewer->outgoingRelationships()->create([
            'target_id' => $author->getKey(),
            'type' => UserRelationshipType::Block,
        ]);

        $this->actingAs($viewer)
            ->put(route('posts.reactions.store', $visible), [
                'type' => PostReactionType::Celebrate->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseEmpty('post_reactions');
    }

    public function test_removal_only_deletes_the_viewers_reaction_even_after_visibility_changes(): void
    {
        Event::fake([PostReactionChanged::class]);

        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create();
        PostReaction::query()->create([
            'post_id' => $post->getKey(),
            'user_id' => $viewer->getKey(),
            'type' => PostReactionType::Like,
        ]);
        PostReaction::query()->create([
            'post_id' => $post->getKey(),
            'user_id' => $other->getKey(),
            'type' => PostReactionType::Celebrate,
        ]);
        $post->update(['hidden_at' => now()]);

        $this->actingAs($viewer)
            ->delete(route('posts.reactions.destroy', $post))
            ->assertRedirect()
            ->assertSessionHas('status', 'Reaction removed.');

        $this->assertDatabaseMissing('post_reactions', [
            'post_id' => $post->getKey(),
            'user_id' => $viewer->getKey(),
        ]);
        $this->assertDatabaseHas('post_reactions', [
            'post_id' => $post->getKey(),
            'user_id' => $other->getKey(),
        ]);
        Event::assertDispatched(
            PostReactionChanged::class,
            fn (PostReactionChanged $event): bool => $event->previousType === PostReactionType::Like
                && $event->type === null,
        );

        $this->actingAs($viewer)
            ->delete('/posts/999999/reaction')
            ->assertRedirect()
            ->assertSessionHas('status', 'No reaction to remove.');
    }

    public function test_feed_and_permalink_expose_only_bounded_aggregates_and_viewer_state(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $third = User::factory()->create();
        $post = Post::factory()->create();
        PostReaction::query()->create([
            'post_id' => $post->getKey(),
            'user_id' => $viewer->getKey(),
            'type' => PostReactionType::Insightful,
        ]);
        PostReaction::query()->create([
            'post_id' => $post->getKey(),
            'user_id' => $other->getKey(),
            'type' => PostReactionType::Like,
        ]);
        PostReaction::query()->create([
            'post_id' => $post->getKey(),
            'user_id' => $third->getKey(),
            'type' => PostReactionType::Like,
        ]);

        $assertReactionProjection = fn (Assert $page): Assert => $page
            ->where('reactionTypes.0.value', PostReactionType::Like->value)
            ->where('posts.0.reactions.total', 3)
            ->where('posts.0.reactions.counts.like', 2)
            ->where('posts.0.reactions.counts.celebrate', 0)
            ->where('posts.0.reactions.counts.insightful', 1)
            ->where('posts.0.reactions.viewerType', PostReactionType::Insightful->value)
            ->missing('posts.0.reactions.users');

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia($assertReactionProjection);

        $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reactionTypes.2.value', PostReactionType::Insightful->value)
                ->where('post.reactions.total', 3)
                ->where('post.reactions.counts.like', 2)
                ->where('post.reactions.viewerType', PostReactionType::Insightful->value)
                ->missing('post.reactions.users'));
    }

    public function test_post_and_user_deletion_cascade_reactions(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        PostReaction::query()->create([
            'post_id' => $post->getKey(),
            'user_id' => $user->getKey(),
            'type' => PostReactionType::Like,
        ]);

        $user->delete();
        $this->assertDatabaseEmpty('post_reactions');

        $secondUser = User::factory()->create();
        PostReaction::query()->create([
            'post_id' => $post->getKey(),
            'user_id' => $secondUser->getKey(),
            'type' => PostReactionType::Like,
        ]);
        $post->delete();

        $this->assertDatabaseEmpty('post_reactions');
    }
}
