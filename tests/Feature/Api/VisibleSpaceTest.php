<?php

namespace Tests\Feature\Api;

use App\Enums\SpaceRole;
use App\Enums\UserRelationshipType;
use App\Models\Space;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibleSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_spaces_index_returns_only_discoverable_spaces_with_viewer_state_and_cursor_pagination(): void
    {
        $viewer = User::factory()->create();
        $public = Space::factory()->create([
            'name' => 'Alpha Public',
            'slug' => 'alpha-public',
            'description' => 'Open community space.',
        ]);
        $privateMember = Space::factory()->private()->create([
            'name' => 'Beta Private',
            'slug' => 'beta-private',
        ]);
        $hiddenModerator = Space::factory()->hidden()->create([
            'name' => 'Gamma Hidden',
            'slug' => 'gamma-hidden',
        ]);
        $inaccessiblePrivate = Space::factory()->private()->create([
            'name' => 'Hidden From Viewer',
            'slug' => 'hidden-from-viewer',
        ]);
        $inaccessibleHidden = Space::factory()->hidden()->create([
            'name' => 'Invisible Circle',
            'slug' => 'invisible-circle',
        ]);

        $privateMember->addMember($viewer);
        $hiddenModerator->addMember($viewer, SpaceRole::Moderator);

        $response = $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.spaces.index', ['limit' => 2]));

        $response
            ->assertOk()
            ->assertJsonPath('meta.limit', 2)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('links.next', fn (?string $url): bool => is_string($url) && str_contains($url, 'cursor='))
            ->assertJsonPath('data.0.slug', $public->slug)
            ->assertJsonPath('data.0.member_count', 1)
            ->assertJsonPath('data.0.viewer.is_member', false)
            ->assertJsonPath('data.0.viewer.role', null)
            ->assertJsonPath('data.0.viewer.can_manage', false)
            ->assertJsonPath('data.1.slug', $privateMember->slug)
            ->assertJsonPath('data.1.viewer.is_member', true)
            ->assertJsonPath('data.1.viewer.role', 'member')
            ->assertJsonPath('data.1.viewer.can_manage', false)
            ->assertJsonMissing(['slug' => $inaccessiblePrivate->slug])
            ->assertJsonMissing(['slug' => $inaccessibleHidden->slug]);

        $nextResponse = $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.spaces.index', [
                'limit' => 2,
                'cursor' => $response->json('meta.next_cursor'),
            ]));

        $nextResponse
            ->assertOk()
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('links.next', null)
            ->assertJsonPath('data.0.slug', $hiddenModerator->slug)
            ->assertJsonPath('data.0.viewer.role', 'moderator')
            ->assertJsonPath('data.0.viewer.can_manage', true);

        $this->assertSame(
            ['slug', 'name', 'description', 'visibility', 'member_count', 'viewer'],
            array_keys($response->json('data.0')),
        );
    }

    public function test_space_detail_uses_the_same_visibility_policy_as_the_web_surface(): void
    {
        $viewer = User::factory()->create();
        $public = Space::factory()->create(['slug' => 'open-space']);
        $private = Space::factory()->private()->create(['slug' => 'private-space']);
        $hidden = Space::factory()->hidden()->create(['slug' => 'hidden-space']);
        $hidden->addMember($viewer, SpaceRole::Owner);

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.spaces.show', $public))
            ->assertOk()
            ->assertJsonPath('data.slug', $public->slug)
            ->assertJsonPath('data.viewer.is_member', false);

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.spaces.show', $private))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.spaces.show', $hidden))
            ->assertOk()
            ->assertJsonPath('data.slug', $hidden->slug)
            ->assertJsonPath('data.visibility', 'hidden')
            ->assertJsonPath('data.viewer.role', 'owner')
            ->assertJsonPath('data.viewer.can_manage', true);
    }

    public function test_block_relationship_does_not_grant_private_space_access_or_expose_member_records(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $space = Space::factory()->private()->create([
            'owner_id' => $owner->getKey(),
            'slug' => 'blocked-private-space',
        ]);

        UserRelationship::query()->create([
            'actor_id' => $owner->getKey(),
            'target_id' => $viewer->getKey(),
            'type' => UserRelationshipType::Block,
        ]);

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.spaces.index'))
            ->assertOk()
            ->assertJsonMissing(['slug' => $space->slug])
            ->assertJsonMissingPath('data.0.owner')
            ->assertJsonMissingPath('data.0.members');

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.spaces.show', $space))
            ->assertForbidden();
    }

    public function test_space_endpoints_require_the_declared_spaces_read_ability(): void
    {
        $viewer = User::factory()->create();
        $space = Space::factory()->create();
        $plainTextToken = $this->token($viewer, ['profile:read']);

        $this->withToken($plainTextToken)
            ->getJson(route('api.v1.spaces.index'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->withToken($plainTextToken)
            ->getJson(route('api.v1.spaces.show', $space))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    /** @param list<string> $abilities */
    private function token(User $user, array $abilities = ['spaces:read']): string
    {
        return $user->createToken('Spaces API test', $abilities, now()->addDays(30))->plainTextToken;
    }
}
