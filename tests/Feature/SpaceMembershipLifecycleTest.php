<?php

namespace Tests\Feature;

use App\Community\SpaceMembershipManager;
use App\Enums\SpaceAuditAction;
use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\SpaceInvitation;
use App\Models\User;
use App\Notifications\SpaceInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpaceMembershipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_moderator_can_invite_with_role_boundaries(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->private()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);

        $this->actingAs($moderator)
            ->post(route('spaces.invitations.store', $space), [
                'email' => '  New.Person@Example.com ',
                'role' => SpaceRole::Member->value,
            ])
            ->assertRedirect(route('spaces.manage', $space));

        $this->assertDatabaseHas('space_invitations', [
            'space_id' => $space->getKey(),
            'email' => 'new.person@example.com',
            'role' => SpaceRole::Member->value,
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'action' => SpaceAuditAction::InvitationSent->value,
        ]);
        Notification::assertSentOnDemand(SpaceInvitationNotification::class);

        $this->actingAs($moderator)
            ->post(route('spaces.invitations.store', $space), [
                'email' => 'another@example.com',
                'role' => SpaceRole::Moderator->value,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('spaces.invitations.store', $space), [
                'email' => 'third@example.com',
                'role' => SpaceRole::Member->value,
            ])
            ->assertForbidden();
    }

    public function test_invitation_only_works_for_the_matching_verified_account(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $wrongUser = User::factory()->create();
        $space = Space::factory()->private()->for($owner, 'owner')->create();
        $token = '';

        $this->actingAs($owner)
            ->post(route('spaces.invitations.store', $space), [
                'email' => $invitee->email,
                'role' => SpaceRole::Moderator->value,
            ])
            ->assertRedirect(route('spaces.manage', $space));

        Notification::assertSentOnDemand(
            SpaceInvitationNotification::class,
            function (SpaceInvitationNotification $notification) use (&$token): bool {
                $path = (string) parse_url($notification->acceptUrl, PHP_URL_PATH);
                $token = Str::afterLast($path, '/');

                return strlen($token) === 64;
            },
        );

        $this->assertNotSame('', $token);

        $this->actingAs($wrongUser)
            ->get(route('space-invitations.show', ['token' => $token]))
            ->assertForbidden();

        $this->actingAs($invitee)
            ->get(route('space-invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('space-invitations/show')
                ->where('invitation.space.name', $space->name)
                ->where('invitation.role', SpaceRole::Moderator->value)
                ->where('invitation.available', true));

        $this->actingAs($invitee)
            ->post(route('space-invitations.accept', ['token' => $token]))
            ->assertRedirect(route('spaces.show', $space));

        $this->assertDatabaseHas('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $invitee->getKey(),
            'role' => SpaceRole::Moderator->value,
        ]);
        $this->assertDatabaseHas('space_invitations', [
            'space_id' => $space->getKey(),
            'accepted_by' => $invitee->getKey(),
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $invitee->getKey(),
            'subject_user_id' => $invitee->getKey(),
            'action' => SpaceAuditAction::InvitationAccepted->value,
        ]);
    }

    public function test_expired_and_revoked_invitations_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $space = Space::factory()->private()->for($owner, 'owner')->create();
        $expiredToken = Str::random(64);
        $revokedToken = Str::random(64);

        SpaceInvitation::query()->create([
            'space_id' => $space->getKey(),
            'invited_by' => $owner->getKey(),
            'email' => $invitee->email,
            'role' => SpaceRole::Member,
            'token_hash' => hash('sha256', $expiredToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($invitee)
            ->post(route('space-invitations.accept', ['token' => $expiredToken]))
            ->assertGone();

        SpaceInvitation::query()->where('space_id', $space->getKey())->delete();
        SpaceInvitation::query()->create([
            'space_id' => $space->getKey(),
            'invited_by' => $owner->getKey(),
            'email' => $invitee->email,
            'role' => SpaceRole::Member,
            'token_hash' => hash('sha256', $revokedToken),
            'expires_at' => now()->addDay(),
            'revoked_at' => now(),
        ]);

        $this->actingAs($invitee)
            ->post(route('space-invitations.accept', ['token' => $revokedToken]))
            ->assertSessionHasErrors('invitation');

        $this->assertDatabaseMissing('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $invitee->getKey(),
        ]);
    }

    public function test_only_owner_can_promote_or_demote_moderators(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);

        $this->actingAs($moderator)
            ->patch(route('spaces.members.roles.update', [$space, $member]), [
                'role' => SpaceRole::Moderator->value,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('spaces.members.roles.update', [$space, $member]), [
                'role' => SpaceRole::Moderator->value,
            ])
            ->assertRedirect(route('spaces.manage', $space));

        $this->assertDatabaseHas('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $member->getKey(),
            'role' => SpaceRole::Moderator->value,
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $owner->getKey(),
            'subject_user_id' => $member->getKey(),
            'action' => SpaceAuditAction::MemberRoleChanged->value,
        ]);
    }

    public function test_owner_can_transfer_ownership_to_an_existing_member(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($newOwner);

        $this->actingAs($owner)
            ->put(route('spaces.owner.update', $space), ['member_id' => $outsider->getKey()])
            ->assertSessionHasErrors('member_id');

        $this->actingAs($owner)
            ->put(route('spaces.owner.update', $space), ['member_id' => $newOwner->getKey()])
            ->assertRedirect(route('spaces.manage', $space));

        $this->assertDatabaseHas('spaces', [
            'id' => $space->getKey(),
            'owner_id' => $newOwner->getKey(),
        ]);
        $this->assertDatabaseHas('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $owner->getKey(),
            'role' => SpaceRole::Moderator->value,
        ]);
        $this->assertDatabaseHas('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $newOwner->getKey(),
            'role' => SpaceRole::Owner->value,
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $owner->getKey(),
            'subject_user_id' => $newOwner->getKey(),
            'action' => SpaceAuditAction::OwnershipTransferred->value,
        ]);
    }

    public function test_moderator_can_remove_members_with_a_reason_but_not_privileged_members(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $otherModerator = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($otherModerator, SpaceRole::Moderator);
        $space->addMember($member);

        $this->actingAs($moderator)
            ->delete(route('spaces.members.destroy', [$space, $member]), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($moderator)
            ->delete(route('spaces.members.destroy', [$space, $otherModerator]), ['reason' => 'Repeated abuse'])
            ->assertForbidden();

        $this->actingAs($moderator)
            ->delete(route('spaces.members.destroy', [$space, $member]), ['reason' => '  Repeated personal attacks.  '])
            ->assertRedirect(route('spaces.manage', $space));

        $this->assertDatabaseMissing('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $member->getKey(),
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'subject_user_id' => $member->getKey(),
            'action' => SpaceAuditAction::MemberRemoved->value,
            'reason' => 'Repeated personal attacks.',
        ]);
    }

    public function test_management_page_is_limited_to_owner_and_moderators(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->hidden()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);

        $this->actingAs($member)
            ->get(route('spaces.manage', $space))
            ->assertForbidden();

        $this->actingAs($moderator)
            ->get(route('spaces.manage', $space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('spaces/manage')
                ->where('space.name', $space->name)
                ->has('members', 3)
                ->where('permissions.canInviteModerators', false));
    }

    public function test_domain_actions_fail_closed_without_privileged_membership(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $target = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($member);
        $space->addMember($target);
        $manager = app(SpaceMembershipManager::class);

        try {
            $manager->invite($space, $member, 'new@example.com', SpaceRole::Member);
            $this->fail('A regular member sent an invitation through the domain service.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('space_invitations', 0);
        }

        try {
            $manager->removeMember($space, $target, $member, 'Not authorized');
            $this->fail('A regular member removed another member through the domain service.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('space_members', [
                'space_id' => $space->getKey(),
                'user_id' => $target->getKey(),
            ]);
        }
    }
}
