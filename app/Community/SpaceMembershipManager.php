<?php

namespace App\Community;

use App\Enums\SpaceAuditAction;
use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\SpaceInvitation;
use App\Models\User;
use App\Notifications\SpaceInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

final class SpaceMembershipManager
{
    public function invite(Space $space, User $actor, string $email, SpaceRole $role): SpaceInvitation
    {
        $email = Str::lower(trim($email));

        if ($role === SpaceRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Ownership can only be transferred to an existing member.',
            ]);
        }

        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($space, $actor, $email, $role, $token): SpaceInvitation {
            $lockedSpace = Space::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();
            $actorRole = $lockedSpace->roleFor($actor);

            if ($actorRole !== SpaceRole::Owner
                && ! ($actorRole === SpaceRole::Moderator && $role === SpaceRole::Member)) {
                throw new AuthorizationException('You cannot send an invitation with this role.');
            }

            $existingUser = User::query()->where('email', $email)->first();

            if ($existingUser instanceof User && $lockedSpace->hasMember($existingUser)) {
                throw ValidationException::withMessages([
                    'email' => 'This person is already a member of the space.',
                ]);
            }

            $invitation = SpaceInvitation::query()->updateOrCreate(
                ['space_id' => $lockedSpace->getKey(), 'email' => $email],
                [
                    'invited_by' => $actor->getKey(),
                    'role' => $role,
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addDays(7),
                    'accepted_at' => null,
                    'accepted_by' => null,
                    'revoked_at' => null,
                ],
            );

            $this->record(
                $lockedSpace,
                $actor,
                SpaceAuditAction::InvitationSent,
                context: ['invitation_id' => $invitation->getKey(), 'role' => $role->value],
            );

            return $invitation;
        });

        Notification::route('mail', $email)->notify(
            new SpaceInvitationNotification(
                $space,
                $actor,
                route('space-invitations.show', ['token' => $token]),
                $invitation->expires_at,
            ),
        );

        return $invitation;
    }

    public function accept(string $token, User $user): Space
    {
        return DB::transaction(function () use ($token, $user): Space {
            $invitation = SpaceInvitation::query()
                ->with('space')
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $invitation instanceof SpaceInvitation) {
                throw (new ModelNotFoundException)->setModel(SpaceInvitation::class);
            }

            if (! hash_equals($invitation->email, Str::lower($user->email))) {
                throw new AuthorizationException('This invitation belongs to another account.');
            }

            if ($invitation->expires_at->isPast()) {
                throw new GoneHttpException('This invitation has expired.');
            }

            if (! $invitation->isPending()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is no longer available.',
                ]);
            }

            $space = $invitation->space;

            if ($space->hasMember($user)) {
                throw ValidationException::withMessages([
                    'invitation' => 'You are already a member of this space.',
                ]);
            }

            $space->addMember($user, $invitation->role);
            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_by' => $user->getKey(),
            ])->save();

            $this->record(
                $space,
                $user,
                SpaceAuditAction::InvitationAccepted,
                $user,
                context: ['invitation_id' => $invitation->getKey(), 'role' => $invitation->role->value],
            );

            return $space;
        });
    }

    public function revokeInvitation(Space $space, SpaceInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($space, $invitation, $actor): void {
            $lockedSpace = Space::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();
            $lockedInvitation = SpaceInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $actorRole = $lockedSpace->roleFor($actor);
            $canRevoke = $actorRole === SpaceRole::Owner
                || ($actorRole === SpaceRole::Moderator && $lockedInvitation->role === SpaceRole::Member);

            if ($lockedInvitation->space_id !== $lockedSpace->getKey() || ! $lockedInvitation->isPending()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is no longer available.',
                ]);
            }

            if (! $canRevoke) {
                throw new AuthorizationException('You cannot cancel this invitation.');
            }

            $lockedInvitation->forceFill(['revoked_at' => now()])->save();

            $this->record(
                $lockedSpace,
                $actor,
                SpaceAuditAction::InvitationRevoked,
                context: ['invitation_id' => $lockedInvitation->getKey(), 'role' => $lockedInvitation->role->value],
            );
        });
    }

    public function updateRole(Space $space, User $member, User $actor, SpaceRole $role): void
    {
        if ($role === SpaceRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Use the ownership transfer action to choose a new owner.',
            ]);
        }

        DB::transaction(function () use ($space, $member, $actor, $role): void {
            $lockedSpace = Space::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedSpace->owner_id !== $actor->getKey()) {
                throw new AuthorizationException('Only the owner can change member roles.');
            }

            $currentRole = $lockedSpace->roleFor($member);

            if (! $currentRole instanceof SpaceRole) {
                throw ValidationException::withMessages(['member' => 'This person is not a member of the space.']);
            }

            if ($currentRole === SpaceRole::Owner) {
                throw new AuthorizationException('The owner role cannot be changed here.');
            }

            if ($currentRole === $role) {
                return;
            }

            $lockedSpace->members()->updateExistingPivot($member->getKey(), ['role' => $role->value]);

            $this->record(
                $lockedSpace,
                $actor,
                SpaceAuditAction::MemberRoleChanged,
                $member,
                context: ['from' => $currentRole->value, 'to' => $role->value],
            );
        });
    }

    public function transferOwnership(Space $space, User $newOwner, User $actor): void
    {
        DB::transaction(function () use ($space, $newOwner, $actor): void {
            /** @var Space $lockedSpace */
            $lockedSpace = Space::query()->lockForUpdate()->findOrFail($space->getKey());

            if ($lockedSpace->owner_id !== $actor->getKey()) {
                throw new AuthorizationException('Only the current owner can transfer ownership.');
            }

            if (! $lockedSpace->hasMember($newOwner)) {
                throw ValidationException::withMessages([
                    'member_id' => 'Ownership can only be transferred to an existing member.',
                ]);
            }

            if ($newOwner->getKey() === $actor->getKey()) {
                throw ValidationException::withMessages([
                    'member_id' => 'This person already owns the space.',
                ]);
            }

            $lockedSpace->members()->updateExistingPivot($actor->getKey(), ['role' => SpaceRole::Moderator->value]);
            $lockedSpace->members()->updateExistingPivot($newOwner->getKey(), ['role' => SpaceRole::Owner->value]);
            $lockedSpace->forceFill(['owner_id' => $newOwner->getKey()])->save();

            $this->record(
                $lockedSpace,
                $actor,
                SpaceAuditAction::OwnershipTransferred,
                $newOwner,
                context: ['previous_owner_id' => $actor->getKey()],
            );
        });
    }

    public function removeMember(Space $space, User $member, User $actor, string $reason): void
    {
        DB::transaction(function () use ($space, $member, $actor, $reason): void {
            $lockedSpace = Space::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();
            $actorRole = $lockedSpace->roleFor($actor);
            $memberRole = $lockedSpace->roleFor($member);
            $canRemove = $actor->isNot($member)
                && $memberRole !== null
                && $memberRole !== SpaceRole::Owner
                && ($actorRole === SpaceRole::Owner
                    || ($actorRole === SpaceRole::Moderator && $memberRole === SpaceRole::Member));

            if (! $canRemove) {
                throw new AuthorizationException('You cannot remove this member.');
            }

            $lockedSpace->members()->detach($member->getKey());

            $this->record(
                $lockedSpace,
                $actor,
                SpaceAuditAction::MemberRemoved,
                $member,
                trim($reason),
            );
        });
    }

    /** @param array<string, mixed>|null $context */
    private function record(
        Space $space,
        User $actor,
        SpaceAuditAction $action,
        ?User $subject = null,
        ?string $reason = null,
        ?array $context = null,
    ): void {
        SpaceAuditLog::query()->create([
            'space_id' => $space->getKey(),
            'actor_id' => $actor->getKey(),
            'subject_user_id' => $subject?->getKey(),
            'action' => $action,
            'reason' => $reason,
            'context' => $context,
        ]);
    }
}
