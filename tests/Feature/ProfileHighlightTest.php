<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Events\ProfilePostHighlightChanged;
use App\Models\Post;
use App\Models\ProfilePostHighlight;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileHighlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_pin_and_unpin_their_post_while_another_member_cannot(): void
    {
        Event::fake([ProfilePostHighlightChanged::class]);

        $author = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $post = Post::factory()->for($space)->for($author, 'author')->create();

        $this->actingAs($other)
            ->put(route('people.posts.highlights.store', [$author, $post]))
            ->assertForbidden();

        $this->actingAs($author)
            ->put(route('people.posts.highlights.store', [$author, $post]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Post pinned to your profile.');

        $this->assertDatabaseHas('profile_post_highlights', [
            'user_id' => $author->getKey(),
            'post_id' => $post->getKey(),
        ]);
        Event::assertDispatched(
            ProfilePostHighlightChanged::class,
            fn (ProfilePostHighlightChanged $event): bool => $event->post->is($post)
                && $event->actor->is($author)
                && $event->highlighted,
        );

        $this->actingAs($author)
            ->delete(route('people.posts.highlights.destroy', [$author, $post]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Post removed from your profile highlights.');

        $this->assertDatabaseEmpty('profile_post_highlights');
        Event::assertDispatched(
            ProfilePostHighlightChanged::class,
            fn (ProfilePostHighlightChanged $event): bool => $event->post->is($post)
                && $event->actor->is($author)
                && ! $event->highlighted,
        );
    }

    public function test_pinning_is_idempotent_and_bounded_to_three_posts(): void
    {
        Event::fake([ProfilePostHighlightChanged::class]);

        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $posts = Post::factory()->count(4)->for($space)->for($author, 'author')->create();

        foreach ($posts->take(3) as $post) {
            $this->actingAs($author)
                ->put(route('people.posts.highlights.store', [$author, $post]))
                ->assertRedirect();
        }

        $this->actingAs($author)
            ->put(route('people.posts.highlights.store', [$author, $posts[0]]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Post is already pinned.');

        $this->actingAs($author)
            ->put(route('people.posts.highlights.store', [$author, $posts[3]]))
            ->assertSessionHasErrors([
                'highlight' => 'Your profile can feature up to three posts. Remove one before adding another.',
            ]);

        $this->assertDatabaseCount('profile_post_highlights', 3);
        Event::assertDispatchedTimes(ProfilePostHighlightChanged::class, 3);
    }

    public function test_only_published_visible_posts_from_the_scoped_profile_can_be_pinned(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $draft = Post::factory()->for($space)->for($author, 'author')->create([
            'published_at' => null,
        ]);
        $hidden = Post::factory()->for($space)->for($author, 'author')->create([
            'hidden_at' => now(),
        ]);
        $otherPost = Post::factory()->for($space)->for($other, 'author')->create();

        foreach ([$draft, $hidden] as $post) {
            $this->actingAs($author)
                ->put(route('people.posts.highlights.store', [$author, $post]))
                ->assertForbidden();
        }

        $this->actingAs($author)
            ->put(route('people.posts.highlights.store', [$author, $otherPost]))
            ->assertNotFound();

        $this->assertDatabaseEmpty('profile_post_highlights');
    }

    public function test_profile_page_reapplies_visibility_and_keeps_activity_chronological(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $public = Space::factory()->for($profile, 'owner')->create();
        $private = Space::factory()->private()->for($profile, 'owner')->create();
        $older = Post::factory()->for($public)->for($profile, 'author')->create([
            'body' => 'Older public post',
            'published_at' => now()->subHour(),
        ]);
        $newer = Post::factory()->for($public)->for($profile, 'author')->create([
            'body' => 'Newer public post',
            'published_at' => now(),
        ]);
        $privatePost = Post::factory()->for($private)->for($profile, 'author')->create([
            'body' => 'Private highlighted post',
        ]);
        ProfilePostHighlight::query()->create([
            'user_id' => $profile->getKey(),
            'post_id' => $newer->getKey(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        ProfilePostHighlight::query()->create([
            'user_id' => $profile->getKey(),
            'post_id' => $older->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ProfilePostHighlight::query()->create([
            'user_id' => $profile->getKey(),
            'post_id' => $privatePost->getKey(),
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $this->actingAs($viewer)
            ->get(route('people.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', $newer->getKey())
                ->where('posts.1.id', $older->getKey())
                ->where('highlights.0.id', $older->getKey())
                ->where('highlights.1.id', $newer->getKey())
                ->where('highlights.0.isProfileHighlighted', true)
                ->where('highlights.0.canManageProfileHighlight', false)
                ->where('profileHighlightLimitReached', false)
                ->has('highlights', 2));
    }

    public function test_hiding_unpublishing_or_deleting_a_post_removes_its_profile_highlight(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $posts = Post::factory()->count(3)->for($space)->for($author, 'author')->create();

        foreach ($posts as $post) {
            ProfilePostHighlight::query()->create([
                'user_id' => $author->getKey(),
                'post_id' => $post->getKey(),
            ]);
        }

        $posts[0]->update(['hidden_at' => now()]);
        $this->assertDatabaseMissing('profile_post_highlights', [
            'post_id' => $posts[0]->getKey(),
        ]);

        $posts[1]->update(['published_at' => null]);
        $this->assertDatabaseMissing('profile_post_highlights', [
            'post_id' => $posts[1]->getKey(),
        ]);

        $posts[2]->delete();
        $this->assertDatabaseEmpty('profile_post_highlights');
    }
}
