<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfilePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_unverified_users_cannot_open_people_pages(): void
    {
        $profile = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);

        $this->get(route('people.index'))->assertRedirect(route('login'));
        $this->get(route('people.show', $profile))->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->get(route('people.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_public_profiles_are_visible_but_private_profiles_are_owner_only(): void
    {
        $viewer = User::factory()->create();
        $public = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $private = User::factory()->create(['profile_visibility' => ProfileVisibility::Private]);

        $this->actingAs($viewer)->get(route('people.show', $public))->assertOk();
        $this->actingAs($viewer)->get(route('people.show', $private))->assertForbidden();
        $this->actingAs($private)->get(route('people.show', $private))->assertOk();
    }

    public function test_members_only_profiles_require_a_shared_space(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create(['profile_visibility' => ProfileVisibility::Members]);
        $space = Space::factory()->private()->create();

        $space->addMember($profile);

        $this->actingAs($viewer)->get(route('people.show', $profile))->assertForbidden();

        $space->addMember($viewer);

        $this->actingAs($viewer)->get(route('people.show', $profile))->assertOk();
    }

    public function test_directory_respects_discovery_and_visibility_settings(): void
    {
        $viewer = User::factory()->create();
        User::factory()->create([
            'name' => 'Public Person',
            'profile_visibility' => ProfileVisibility::Public,
            'is_discoverable' => true,
        ]);
        User::factory()->create([
            'name' => 'Hidden From Discovery',
            'profile_visibility' => ProfileVisibility::Public,
            'is_discoverable' => false,
        ]);
        User::factory()->create([
            'name' => 'Private Person',
            'profile_visibility' => ProfileVisibility::Private,
            'is_discoverable' => true,
        ]);
        $shared = User::factory()->create([
            'name' => 'Shared Person',
            'profile_visibility' => ProfileVisibility::Members,
            'is_discoverable' => true,
        ]);
        User::factory()->create([
            'name' => 'Not Shared',
            'profile_visibility' => ProfileVisibility::Members,
            'is_discoverable' => true,
        ]);
        $space = Space::factory()->for($viewer, 'owner')->private()->create();
        $space->addMember($shared);

        $this->actingAs($viewer)
            ->get(route('people.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/index')
                ->has('people', 2)
                ->where('people.0.name', 'Public Person')
                ->where('people.1.name', 'Shared Person')
                ->where('people.1.sharedSpaceCount', 1));

    }

    public function test_profile_only_exposes_spaces_and_posts_visible_to_the_viewer(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create([
            'name' => 'Visible Member',
            'headline' => 'Thoughtful community builder',
            'profile_visibility' => ProfileVisibility::Public,
            'bio' => 'A public bio.',
        ]);
        $public = Space::factory()->create(['name' => 'Public Space']);
        $private = Space::factory()->private()->create(['name' => 'Private Space']);
        $public->addMember($profile);
        $private->addMember($profile);

        Post::factory()->for($public)->for($profile, 'author')->create(['body' => 'Visible post']);
        Post::factory()->for($private)->for($profile, 'author')->create(['body' => 'Private post']);

        $this->actingAs($viewer)
            ->get(route('people.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/show')
                ->where('profile.name', 'Visible Member')
                ->where('profile.headline', 'Thoughtful community builder')
                ->where('profile.bio', 'A public bio.')
                ->missing('profile.email')
                ->where('stats.visibleSpaces', 1)
                ->where('stats.visiblePosts', 1)
                ->has('spaces', 1)
                ->where('spaces.0.name', 'Public Space')
                ->has('posts', 1)
                ->where('posts.0.body', 'Visible post'));
    }
}
