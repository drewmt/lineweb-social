<?php

namespace Tests\Feature;

use App\Enums\SpaceAuditAction;
use App\Enums\SpaceRole;
use App\Events\PostHighlightChanged;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpacePostHighlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpaceHighlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_moderator_can_highlight_while_members_and_outsiders_cannot(): void
    {
        Event::fake([PostHighlightChanged::class]);

        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);
        $first = Post::factory()->for($space)->create();
        $second = Post::factory()->for($space)->create();
        $third = Post::factory()->for($space)->create();

        $this->actingAs($owner)
            ->put(route('spaces.posts.highlights.store', [$space, $first]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Post added to Space highlights.');

        $this->actingAs($moderator)
            ->put(route('spaces.posts.highlights.store', [$space, $second]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Post added to Space highlights.');

        foreach ([$member, $outsider] as $actor) {
            $this->actingAs($actor)
                ->put(route('spaces.posts.highlights.store', [$space, $third]))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('space_post_highlights', 2);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $owner->getKey(),
            'action' => SpaceAuditAction::PostHighlighted->value,
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'action' => SpaceAuditAction::PostHighlighted->value,
        ]);
        Event::assertDispatchedTimes(PostHighlightChanged::class, 2);
    }

    public function test_highlighting_is_idempotent_and_bounded_to_three_posts(): void
    {
        Event::fake([PostHighlightChanged::class]);

        $owner = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $posts = Post::factory()->count(4)->for($space)->create();

        foreach ($posts->take(3) as $post) {
            $this->actingAs($owner)
                ->put(route('spaces.posts.highlights.store', [$space, $post]))
                ->assertRedirect();
        }

        $this->actingAs($owner)
            ->put(route('spaces.posts.highlights.store', [$space, $posts[0]]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Post is already highlighted.');

        $this->actingAs($owner)
            ->put(route('spaces.posts.highlights.store', [$space, $posts[3]]))
            ->assertSessionHasErrors([
                'highlight' => 'A Space can feature up to three highlights. Remove one before adding another.',
            ]);

        $this->assertDatabaseCount('space_post_highlights', 3);
        $this->assertDatabaseCount('space_audit_logs', 3);
        Event::assertDispatchedTimes(PostHighlightChanged::class, 3);
    }

    public function test_only_published_visible_posts_from_the_scoped_space_can_be_highlighted(): void
    {
        $owner = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $otherSpace = Space::factory()->for($owner, 'owner')->create();
        $draft = Post::factory()->for($space)->create(['published_at' => null]);
        $hidden = Post::factory()->for($space)->create(['hidden_at' => now()]);
        $otherPost = Post::factory()->for($otherSpace)->create();

        foreach ([$draft, $hidden] as $post) {
            $this->actingAs($owner)
                ->put(route('spaces.posts.highlights.store', [$space, $post]))
                ->assertForbidden();
        }

        $this->actingAs($owner)
            ->put(route('spaces.posts.highlights.store', [$space, $otherPost]))
            ->assertNotFound();

        $this->assertDatabaseEmpty('space_post_highlights');
    }

    public function test_moderator_can_remove_a_highlight_after_the_post_becomes_hidden(): void
    {
        Event::fake([PostHighlightChanged::class]);

        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $post = Post::factory()->for($space)->create();
        SpacePostHighlight::query()->create([
            'space_id' => $space->getKey(),
            'post_id' => $post->getKey(),
            'highlighted_by' => $owner->getKey(),
        ]);
        $post->update(['hidden_at' => now()]);

        $this->actingAs($moderator)
            ->delete(route('spaces.posts.highlights.destroy', [$space, $post]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Post removed from Space highlights.');

        $this->actingAs($moderator)
            ->delete(route('spaces.posts.highlights.destroy', [$space, $post]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Post was not highlighted.');

        $this->assertDatabaseEmpty('space_post_highlights');
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'action' => SpaceAuditAction::PostUnhighlighted->value,
        ]);
        Event::assertDispatchedTimes(PostHighlightChanged::class, 1);
        Event::assertDispatched(
            PostHighlightChanged::class,
            fn (PostHighlightChanged $event): bool => $event->post->is($post)
                && $event->actor->is($moderator)
                && $event->highlighted === false,
        );
    }

    public function test_space_page_exposes_recent_highlights_without_changing_timeline_order(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->private()->create();
        $space->addMember($viewer);
        $oldPost = Post::factory()->for($space)->create([
            'body' => 'Older timeline post',
            'published_at' => now()->subHour(),
        ]);
        $newPost = Post::factory()->for($space)->create([
            'body' => 'Newer timeline post',
            'published_at' => now(),
        ]);
        SpacePostHighlight::query()->create([
            'space_id' => $space->getKey(),
            'post_id' => $newPost->getKey(),
            'highlighted_by' => $owner->getKey(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        SpacePostHighlight::query()->create([
            'space_id' => $space->getKey(),
            'post_id' => $oldPost->getKey(),
            'highlighted_by' => $owner->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('spaces.show', $space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', $newPost->getKey())
                ->where('posts.1.id', $oldPost->getKey())
                ->where('posts.0.isHighlighted', true)
                ->where('highlights.0.id', $oldPost->getKey())
                ->where('highlights.1.id', $newPost->getKey())
                ->where('highlights.0.isHighlighted', true)
                ->has('highlights', 2));

        $this->actingAs(User::factory()->create())
            ->get(route('spaces.show', $space))
            ->assertForbidden();
    }

    public function test_deleting_a_post_or_space_cascades_highlights(): void
    {
        $owner = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $first = Post::factory()->for($space)->create();
        $second = Post::factory()->for($space)->create();

        foreach ([$first, $second] as $post) {
            SpacePostHighlight::query()->create([
                'space_id' => $space->getKey(),
                'post_id' => $post->getKey(),
                'highlighted_by' => $owner->getKey(),
            ]);
        }

        $first->delete();
        $this->assertDatabaseCount('space_post_highlights', 1);

        $space->delete();
        $this->assertDatabaseEmpty('space_post_highlights');
    }
}
