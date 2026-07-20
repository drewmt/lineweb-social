<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_mute_or_block_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('people.show', $user))
            ->post(route('people.mute', $user))
            ->assertSessionHasErrors('relationship');

        $this->actingAs($user)
            ->from(route('people.show', $user))
            ->post(route('people.block', $user))
            ->assertSessionHasErrors('relationship');

        $this->assertDatabaseCount('user_relationships', 0);
    }

    public function test_muting_hides_target_posts_but_keeps_profiles_discoverable(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create([
            'name' => 'Muted Person',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $space = Space::factory()->create();
        $space->addMember($target);
        Post::factory()->for($space)->for($target, 'author')->create([
            'body' => 'Muted feed post',
        ]);

        $this->actingAs($viewer)
            ->post(route('people.mute', $target))
            ->assertRedirect();

        $this->assertDatabaseHas('user_relationships', [
            'actor_id' => $viewer->getKey(),
            'target_id' => $target->getKey(),
            'type' => UserRelationshipType::Mute->value,
        ]);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertInertia(fn (Assert $page) => $page->has('posts', 0));

        $this->actingAs($viewer)
            ->get(route('people.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('people', 1)
                ->where('people.0.name', 'Muted Person'));

        $this->actingAs($viewer)
            ->get(route('people.show', $target))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('profile.isMuted', true));

        $this->actingAs($viewer)
            ->delete(route('people.unmute', $target))
            ->assertRedirect();

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts', 1)
                ->where('posts.0.body', 'Muted feed post'));
    }

    public function test_blocking_creates_a_mutual_profile_discovery_and_feed_boundary(): void
    {
        $viewer = User::factory()->create([
            'name' => 'Viewer',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $target = User::factory()->create([
            'name' => 'Blocked Person',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $space = Space::factory()->for($viewer, 'owner')->create();
        $space->addMember($target);
        Post::factory()->for($space)->for($viewer, 'author')->create(['body' => 'Viewer post']);
        Post::factory()->for($space)->for($target, 'author')->create(['body' => 'Target post']);

        $this->actingAs($viewer)->post(route('people.mute', $target));
        $this->actingAs($viewer)
            ->post(route('people.block', $target))
            ->assertRedirect(route('people.index'));

        $this->assertDatabaseMissing('user_relationships', [
            'actor_id' => $viewer->getKey(),
            'target_id' => $target->getKey(),
            'type' => UserRelationshipType::Mute->value,
        ]);
        $this->assertDatabaseHas('user_relationships', [
            'actor_id' => $viewer->getKey(),
            'target_id' => $target->getKey(),
            'type' => UserRelationshipType::Block->value,
        ]);

        $this->actingAs($viewer)->get(route('people.show', $target))->assertForbidden();
        $this->actingAs($target)->get(route('people.show', $viewer))->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('people.index'))
            ->assertInertia(fn (Assert $page) => $page->has('people', 0));
        $this->actingAs($target)
            ->get(route('people.index'))
            ->assertInertia(fn (Assert $page) => $page->has('people', 0));

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts', 1)
                ->where('posts.0.body', 'Viewer post'));
        $this->actingAs($target)
            ->get(route('feed'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts', 1)
                ->where('posts.0.body', 'Target post'));

        $this->actingAs($viewer)
            ->delete(route('people.unblock', $target))
            ->assertRedirect();

        $this->actingAs($viewer)->get(route('people.show', $target))->assertOk();
        $this->actingAs($target)->get(route('people.show', $viewer))->assertOk();
    }

    public function test_safety_settings_lists_outgoing_relationships_without_private_profile_data(): void
    {
        $viewer = User::factory()->create();
        $muted = User::factory()->create(['name' => 'Muted Member']);
        $blocked = User::factory()->create(['name' => 'Blocked Member']);

        $this->actingAs($viewer)->post(route('people.mute', $muted));
        $this->actingAs($viewer)->post(route('people.block', $blocked));

        $this->actingAs($viewer)
            ->get(route('safety.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/safety')
                ->has('relationships', 2)
                ->where('relationships.0.type', 'block')
                ->where('relationships.0.person.name', 'Blocked Member')
                ->missing('relationships.0.person.email')
                ->where('relationships.1.type', 'mute')
                ->where('relationships.1.person.name', 'Muted Member'));
    }
}
