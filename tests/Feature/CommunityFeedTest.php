<?php

namespace Tests\Feature;

use App\Enums\SpaceRole;
use App\Events\PostPublished;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_feed(): void
    {
        $this->get(route('feed'))->assertRedirect(route('login'));
    }

    public function test_unverified_members_cannot_open_the_feed(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('feed'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_feed_contains_public_posts_and_member_only_posts_in_chronological_order(): void
    {
        $member = User::factory()->create();
        $author = User::factory()->create();
        $public = Space::factory()->create(['name' => 'Public square']);
        $private = Space::factory()->private()->create(['name' => 'Private circle']);
        $hidden = Space::factory()->hidden()->create(['name' => 'Hidden room']);

        $private->addMember($member);

        Post::factory()->for($public)->for($author, 'author')->create([
            'body' => 'Older public post',
            'published_at' => now()->subHour(),
        ]);
        Post::factory()->for($private)->for($author, 'author')->create([
            'body' => 'Newest private post',
            'published_at' => now(),
        ]);
        Post::factory()->for($hidden)->for($author, 'author')->create([
            'body' => 'Invisible hidden post',
            'published_at' => now()->addMinute(),
        ]);

        $this->actingAs($member)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('feed/index')
                ->has('posts', 2)
                ->where('posts.0.body', 'Newest private post')
                ->where('posts.1.body', 'Older public post')
                ->has('spaces', 2)
                ->where('selectedSpace', null));
    }

    public function test_non_members_cannot_discover_restricted_spaces_or_publish_to_public_spaces(): void
    {
        $user = User::factory()->create();
        $public = Space::factory()->create();
        $private = Space::factory()->private()->create();
        $hidden = Space::factory()->hidden()->create();

        $this->actingAs($user)->get(route('spaces.show', $public))->assertOk();
        $this->actingAs($user)->get(route('spaces.show', $private))->assertForbidden();
        $this->actingAs($user)->get(route('spaces.show', $hidden))->assertForbidden();

        $this->actingAs($user)
            ->post(route('spaces.posts.store', $public), ['body' => 'Should not publish'])
            ->assertForbidden();

        $this->assertDatabaseMissing('posts', ['body' => 'Should not publish']);
    }

    public function test_verified_members_can_publish_a_valid_post(): void
    {
        Event::fake([PostPublished::class]);

        $member = User::factory()->create();
        $space = Space::factory()->private()->create();
        $space->addMember($member, SpaceRole::Member);

        $this->actingAs($member)
            ->from(route('spaces.show', $space))
            ->post(route('spaces.posts.store', $space), ['body' => '  A useful update.  '])
            ->assertRedirect(route('spaces.show', $space));

        $this->assertDatabaseHas('posts', [
            'space_id' => $space->getKey(),
            'user_id' => $member->getKey(),
            'body' => 'A useful update.',
        ]);

        Event::assertDispatched(PostPublished::class);
    }

    public function test_post_body_is_required_and_limited(): void
    {
        $member = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($member);

        $this->actingAs($member)
            ->post(route('spaces.posts.store', $space), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($member)
            ->post(route('spaces.posts.store', $space), ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');
    }
}
