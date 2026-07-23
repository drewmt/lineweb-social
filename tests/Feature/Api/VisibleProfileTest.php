<?php

namespace Tests\Feature\Api;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Models\Space;
use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisibleProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_uses_the_safe_contract_and_viewer_relationship_state(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create([
            'name' => 'Visible Member',
            'headline' => 'Community builder',
            'bio' => 'A safe public bio.',
            'location' => 'Thessaloniki',
            'website_url' => 'https://www.lineweb.gr',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        UserRelationship::query()->create([
            'actor_id' => $viewer->getKey(),
            'target_id' => $profile->getKey(),
            'type' => UserRelationshipType::Mute,
        ]);
        UserFollow::query()->create([
            'follower_id' => $viewer->getKey(),
            'followed_id' => $profile->getKey(),
        ]);

        $response = $this->withToken($this->token($viewer, ['profiles:read']))
            ->getJson(route('api.v1.profiles.show', $profile));

        $response
            ->assertOk()
            ->assertJsonPath('data.handle', $profile->handle)
            ->assertJsonPath('data.name', 'Visible Member')
            ->assertJsonPath('data.viewer.is_self', false)
            ->assertJsonPath('data.viewer.is_muted', true)
            ->assertJsonPath('data.viewer.is_following', true)
            ->assertJsonPath('data.viewer.can_follow', true)
            ->assertJsonPath('data.stats.followers', 1)
            ->assertJsonPath('data.stats.following', 0)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.profile_visibility')
            ->assertJsonMissingPath('data.is_discoverable');

        $this->assertSame(
            ['handle', 'name', 'headline', 'bio', 'location', 'website_url', 'member_since', 'stats', 'viewer'],
            array_keys($response->json('data')),
        );
    }

    public function test_discovery_opt_out_does_not_hide_an_otherwise_public_direct_profile(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
            'is_discoverable' => false,
        ]);

        $this->withToken($this->token($viewer, ['profiles:read']))
            ->getJson(route('api.v1.profiles.show', $profile))
            ->assertOk();
    }

    public function test_private_and_unshared_member_profiles_are_forbidden(): void
    {
        $viewer = User::factory()->create();
        $private = User::factory()->create(['profile_visibility' => ProfileVisibility::Private]);
        $members = User::factory()->create(['profile_visibility' => ProfileVisibility::Members]);
        $plainTextToken = $this->token($viewer, ['profiles:read']);

        foreach ([$private, $members] as $profile) {
            $this->withToken($plainTextToken)
                ->getJson(route('api.v1.profiles.show', $profile))
                ->assertForbidden()
                ->assertJsonPath('code', 'forbidden');
        }
    }

    public function test_members_profile_is_visible_after_a_shared_space_exists(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create(['profile_visibility' => ProfileVisibility::Members]);
        $space = Space::factory()->private()->create();
        $space->addMember($viewer);
        $space->addMember($profile);

        $this->withToken($this->token($viewer, ['profiles:read']))
            ->getJson(route('api.v1.profiles.show', $profile))
            ->assertOk()
            ->assertJsonPath('data.handle', $profile->handle);
    }

    public function test_a_block_in_either_direction_denies_profile_access(): void
    {
        $viewer = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $profile = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $plainTextToken = $this->token($viewer, ['profiles:read']);

        foreach ([
            [$viewer, $profile],
            [$profile, $viewer],
        ] as [$actor, $target]) {
            UserRelationship::query()->create([
                'actor_id' => $actor->getKey(),
                'target_id' => $target->getKey(),
                'type' => UserRelationshipType::Block,
            ]);

            $this->withToken($plainTextToken)
                ->getJson(route('api.v1.profiles.show', $profile))
                ->assertForbidden();

            UserRelationship::query()->delete();
        }
    }

    public function test_self_profile_is_available_through_the_profiles_scope(): void
    {
        $viewer = User::factory()->create(['profile_visibility' => ProfileVisibility::Private]);

        $this->withToken($this->token($viewer, ['profiles:read']))
            ->getJson(route('api.v1.profiles.show', $viewer))
            ->assertOk()
            ->assertJsonPath('data.viewer.is_self', true)
            ->assertJsonPath('data.viewer.is_muted', false)
            ->assertJsonPath('data.viewer.is_following', false)
            ->assertJsonPath('data.viewer.can_follow', false);
    }

    public function test_missing_profile_uses_the_stable_not_found_envelope(): void
    {
        $viewer = User::factory()->create();

        $response = $this->withToken($this->token($viewer, ['profiles:read']))
            ->getJson('/api/v1/profiles/not-a-real-handle');

        $response
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found')
            ->assertJsonPath('message', 'The requested resource was not found.');
        $this->assertTrue(Str::isUuid($response->json('request_id')));
    }

    public function test_profile_endpoint_requires_its_declared_ability(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);

        $this->withToken($this->token($viewer, ['profile:read']))
            ->getJson(route('api.v1.profiles.show', $profile))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    /** @param list<string> $abilities */
    private function token(User $user, array $abilities): string
    {
        return $user->createToken('API test', $abilities, now()->addDays(30))->plainTextToken;
    }
}
