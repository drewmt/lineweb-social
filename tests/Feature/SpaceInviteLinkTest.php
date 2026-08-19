<?php

namespace Tests\Feature;

use App\Enums\SpaceAuditAction;
use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\SpaceInviteLink;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpaceInviteLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_moderator_can_create_hashed_member_invite_links(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->private()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);

        $response = $this->actingAs($owner)
            ->post(route('spaces.invite-links.store', $space), [
                'label' => 'Founding members',
                'expires_in_days' => 7,
                'max_uses' => 12,
            ])
            ->assertRedirect(route('spaces.manage', $space))
            ->assertSessionHas('spaceInviteLink');

        $inviteLink = SpaceInviteLink::query()->sole();
        $url = $response->getSession()->get('spaceInviteLink')['url'];
        $token = Str::afterLast((string) parse_url($url, PHP_URL_PATH), '/');

        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $inviteLink->token_hash);
        $this->assertSame('Founding members', $inviteLink->label);
        $this->assertSame(12, $inviteLink->max_uses);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $owner->getKey(),
            'action' => SpaceAuditAction::InviteLinkCreated->value,
        ]);

        $this->actingAs($moderator)
            ->post(route('spaces.invite-links.store', $space), [
                'expires_in_days' => 1,
                'max_uses' => 1,
            ])
            ->assertRedirect(route('spaces.manage', $space));

        $this->actingAs($member)
            ->post(route('spaces.invite-links.store', $space), [
                'expires_in_days' => 1,
                'max_uses' => 1,
            ])
            ->assertForbidden();
    }

    public function test_invite_link_creation_enforces_safe_bounds(): void
    {
        $owner = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->post(route('spaces.invite-links.store', $space), [
                'label' => str_repeat('a', 81),
                'expires_in_days' => 31,
                'max_uses' => 101,
            ])
            ->assertSessionHasErrors(['label', 'expires_in_days', 'max_uses']);

        $this->assertDatabaseEmpty('space_invite_links');
    }

    public function test_a_space_cannot_accumulate_more_than_twenty_active_links(): void
    {
        $owner = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();

        foreach (range(1, 20) as $position) {
            $this->createInviteLink(
                $space,
                $owner,
                Str::random(64),
                maxUses: $position,
            );
        }

        $this->actingAs($owner)
            ->post(route('spaces.invite-links.store', $space), [
                'expires_in_days' => 7,
                'max_uses' => 10,
            ])
            ->assertSessionHasErrors('invite_link');

        $this->assertDatabaseCount('space_invite_links', 20);
    }

    public function test_acceptance_requires_an_authenticated_active_verified_account(): void
    {
        $owner = User::factory()->create();
        $space = Space::factory()->private()->for($owner, 'owner')->create();
        $token = Str::random(64);
        $this->createInviteLink($space, $owner, $token);

        $this->post(route('space-invite-links.accept', ['token' => $token]))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('space-invite-links.accept', ['token' => $token]))
            ->assertRedirect(route('verification.notice'));

        $suspended = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reference' => (string) Str::uuid(),
        ]);

        $this->actingAs($suspended)
            ->post(route('space-invite-links.accept', ['token' => $token]))
            ->assertRedirect(route('account.status'));

        $this->assertSame(0, SpaceInviteLink::query()->sole()->uses_count);
    }

    public function test_public_preview_exposes_safe_details_and_preserves_the_invite_for_signup(): void
    {
        $owner = User::factory()->create(['name' => 'Community host']);
        $space = Space::factory()->private()->for($owner, 'owner')->create([
            'name' => 'Makers Circle',
            'description' => 'A private community for independent makers.',
        ]);
        $token = Str::random(64);
        $inviteLink = $this->createInviteLink($space, $owner, $token, maxUses: 5);

        $this->get(route('space-invite-links.show', ['token' => $token]))
            ->assertOk()
            ->assertSessionHas('pending_space_invite', $token)
            ->assertSessionHas('url.intended', route('space-invite-links.show', ['token' => $token]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('space-invite-links/show')
                ->where('inviteLink.space.name', 'Makers Circle')
                ->where('inviteLink.creator', 'Community host')
                ->where('inviteLink.remainingUses', 5)
                ->where('inviteLink.available', true)
                ->where('viewer.signedIn', false));

        $this->assertNotSame($token, $inviteLink->token_hash);
    }

    public function test_acceptance_is_idempotent_and_never_exceeds_the_usage_limit(): void
    {
        $owner = User::factory()->create();
        $firstMember = User::factory()->create();
        $secondMember = User::factory()->create();
        $space = Space::factory()->private()->for($owner, 'owner')->create();
        $token = Str::random(64);
        $inviteLink = $this->createInviteLink($space, $owner, $token, maxUses: 1);

        $this->actingAs($firstMember)
            ->post(route('space-invite-links.accept', ['token' => $token]))
            ->assertRedirect(route('spaces.show', $space));

        $this->assertDatabaseHas('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $firstMember->getKey(),
            'role' => SpaceRole::Member->value,
        ]);
        $this->assertSame(1, $inviteLink->fresh()->uses_count);

        $this->actingAs($firstMember)
            ->post(route('space-invite-links.accept', ['token' => $token]))
            ->assertRedirect(route('spaces.show', $space));

        $this->actingAs($secondMember)
            ->post(route('space-invite-links.accept', ['token' => $token]))
            ->assertSessionHasErrors('invite_link');

        $this->assertDatabaseMissing('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $secondMember->getKey(),
        ]);
        $this->assertSame(1, $inviteLink->fresh()->uses_count);
        $this->assertSame(1, $space->auditLogs()
            ->where('action', SpaceAuditAction::InviteLinkAccepted)
            ->count());
    }

    public function test_moderators_can_revoke_links_but_members_and_other_spaces_cannot(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $otherSpace = Space::factory()->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);
        $inviteLink = $this->createInviteLink($space, $owner, Str::random(64));

        $this->actingAs($member)
            ->delete(route('spaces.invite-links.destroy', [$space, $inviteLink]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('spaces.invite-links.destroy', [$otherSpace, $inviteLink]))
            ->assertNotFound();

        $this->actingAs($moderator)
            ->delete(route('spaces.invite-links.destroy', [$space, $inviteLink]))
            ->assertRedirect(route('spaces.manage', $space));

        $this->assertNotNull($inviteLink->fresh()->revoked_at);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'action' => SpaceAuditAction::InviteLinkRevoked->value,
        ]);
    }

    public function test_unavailable_links_fail_closed_and_clear_matching_pending_state(): void
    {
        $owner = User::factory()->create();
        $space = Space::factory()->private()->for($owner, 'owner')->create();
        $token = Str::random(64);
        $this->createInviteLink($space, $owner, $token, expiresAt: now()->subMinute());

        $this->withSession(['pending_space_invite' => $token])
            ->get(route('space-invite-links.show', ['token' => $token]))
            ->assertOk()
            ->assertSessionMissing('pending_space_invite')
            ->assertInertia(fn (Assert $page) => $page
                ->where('inviteLink.available', false));
    }

    private function createInviteLink(
        Space $space,
        User $creator,
        string $token,
        int $maxUses = 10,
        ?CarbonInterface $expiresAt = null,
    ): SpaceInviteLink {
        return SpaceInviteLink::query()->create([
            'space_id' => $space->getKey(),
            'created_by' => $creator->getKey(),
            'token_hash' => hash('sha256', $token),
            'max_uses' => $maxUses,
            'expires_at' => $expiresAt ?? now()->addWeek(),
        ]);
    }
}
