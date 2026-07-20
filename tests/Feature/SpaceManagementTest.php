<?php

namespace Tests\Feature;

use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_verified_members_can_open_or_create_spaces(): void
    {
        $this->get(route('spaces.index'))->assertRedirect(route('login'));
        $this->post(route('spaces.store'), [])->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->get(route('spaces.index'))
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)
            ->post(route('spaces.store'), ['name' => 'Blocked'])
            ->assertRedirect(route('verification.notice'));
    }

    public function test_directory_only_contains_spaces_the_member_can_discover(): void
    {
        $member = User::factory()->create();
        $public = Space::factory()->create(['name' => 'Open Studio']);
        $private = Space::factory()->private()->create(['name' => 'Client Circle']);
        Space::factory()->hidden()->create(['name' => 'Secret Room']);
        $private->addMember($member);

        $this->actingAs($member)
            ->get(route('spaces.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('spaces/index')
                ->has('spaces', 2)
                ->where('spaces.0.name', 'Client Circle')
                ->where('spaces.0.isMember', true)
                ->where('spaces.1.name', 'Open Studio')
                ->where('spaces.1.isMember', false));

        $this->assertNotSame($public->getKey(), $private->getKey());
    }

    public function test_member_can_create_a_space_and_becomes_its_owner(): void
    {
        $member = User::factory()->create();

        $response = $this->actingAs($member)->post(route('spaces.store'), [
            'name' => '  Makers Circle  ',
            'description' => '  Build useful things together.  ',
            'visibility' => SpaceVisibility::Private->value,
        ]);

        $space = Space::query()->sole();

        $response->assertRedirect(route('spaces.show', $space));
        $this->assertSame('Makers Circle', $space->name);
        $this->assertSame('makers-circle', $space->slug);
        $this->assertSame('Build useful things together.', $space->description);
        $this->assertSame(SpaceVisibility::Private, $space->visibility);
        $this->assertDatabaseHas('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $member->getKey(),
            'role' => SpaceRole::Owner->value,
        ]);
    }

    public function test_duplicate_space_names_receive_stable_unique_slugs(): void
    {
        $member = User::factory()->create();

        foreach (range(1, 2) as $iteration) {
            $this->actingAs($member)->post(route('spaces.store'), [
                'name' => 'Makers Circle',
                'visibility' => SpaceVisibility::Public->value,
            ])->assertRedirect();
        }

        $this->assertDatabaseHas('spaces', ['slug' => 'makers-circle']);
        $this->assertDatabaseHas('spaces', ['slug' => 'makers-circle-2']);
    }

    public function test_space_creation_validates_the_public_contract(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->post(route('spaces.store'), [
                'name' => '',
                'description' => str_repeat('a', 501),
                'visibility' => 'secret',
            ])
            ->assertSessionHasErrors(['name', 'description', 'visibility']);

        $this->assertDatabaseCount('spaces', 0);
    }

    public function test_non_member_can_join_a_public_space_but_not_a_restricted_space(): void
    {
        $member = User::factory()->create();
        $public = Space::factory()->create();
        $private = Space::factory()->private()->create();
        $hidden = Space::factory()->hidden()->create();

        $this->actingAs($member)
            ->post(route('spaces.memberships.store', $public))
            ->assertRedirect(route('spaces.show', $public));

        $this->assertDatabaseHas('space_members', [
            'space_id' => $public->getKey(),
            'user_id' => $member->getKey(),
            'role' => SpaceRole::Member->value,
        ]);

        $this->actingAs($member)
            ->post(route('spaces.memberships.store', $public))
            ->assertForbidden();
        $this->actingAs($member)
            ->post(route('spaces.memberships.store', $private))
            ->assertForbidden();
        $this->actingAs($member)
            ->post(route('spaces.memberships.store', $hidden))
            ->assertForbidden();
    }

    public function test_member_can_leave_but_the_owner_cannot_abandon_a_space(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($member);

        $this->actingAs($member)
            ->delete(route('spaces.memberships.destroy', $space))
            ->assertRedirect(route('spaces.index'));

        $this->assertDatabaseMissing('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $member->getKey(),
        ]);

        $this->actingAs($owner)
            ->delete(route('spaces.memberships.destroy', $space))
            ->assertForbidden();

        $this->assertDatabaseHas('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $owner->getKey(),
            'role' => SpaceRole::Owner->value,
        ]);
    }
}
