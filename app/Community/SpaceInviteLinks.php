<?php

namespace App\Community;

use App\Enums\SpaceAuditAction;
use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\SpaceInviteLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SpaceInviteLinks
{
    private const MAX_ACTIVE_LINKS_PER_SPACE = 20;

    public function create(
        Space $space,
        User $actor,
        ?string $label,
        int $expiresInDays,
        int $maxUses,
    ): SpaceInviteLinkResult {
        $token = Str::random(64);

        $inviteLink = DB::transaction(function () use (
            $space,
            $actor,
            $label,
            $expiresInDays,
            $maxUses,
            $token,
        ): SpaceInviteLink {
            $lockedSpace = Space::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($lockedSpace->roleFor($actor), [SpaceRole::Owner, SpaceRole::Moderator], true)) {
                throw new AuthorizationException('You cannot create invite links for this space.');
            }

            $activeLinks = SpaceInviteLink::query()
                ->where('space_id', $lockedSpace->getKey())
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->whereColumn('uses_count', '<', 'max_uses')
                ->count();

            if ($activeLinks >= self::MAX_ACTIVE_LINKS_PER_SPACE) {
                throw ValidationException::withMessages([
                    'invite_link' => 'Revoke or wait for an existing invite link before creating another.',
                ]);
            }

            $inviteLink = SpaceInviteLink::query()->create([
                'space_id' => $lockedSpace->getKey(),
                'created_by' => $actor->getKey(),
                'label' => filled($label) ? trim((string) $label) : null,
                'token_hash' => hash('sha256', $token),
                'max_uses' => $maxUses,
                'expires_at' => now()->addDays($expiresInDays),
            ]);

            $this->record(
                $lockedSpace,
                $actor,
                SpaceAuditAction::InviteLinkCreated,
                context: [
                    'invite_link_id' => $inviteLink->getKey(),
                    'max_uses' => $inviteLink->max_uses,
                    'expires_at' => $inviteLink->expires_at->toIso8601String(),
                ],
            );

            return $inviteLink;
        });

        return new SpaceInviteLinkResult($inviteLink, $token);
    }

    public function accept(string $token, User $user): Space
    {
        return DB::transaction(function () use ($token, $user): Space {
            $inviteLinkReference = SpaceInviteLink::query()
                ->select(['id', 'space_id'])
                ->where('token_hash', hash('sha256', $token))
                ->first();

            if (! $inviteLinkReference instanceof SpaceInviteLink) {
                throw (new ModelNotFoundException)->setModel(SpaceInviteLink::class);
            }

            $space = Space::query()
                ->whereKey($inviteLinkReference->space_id)
                ->lockForUpdate()
                ->firstOrFail();
            $inviteLink = SpaceInviteLink::query()
                ->whereKey($inviteLinkReference->getKey())
                ->where('space_id', $space->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($space->hasMember($user)) {
                return $space;
            }

            if (! $inviteLink->isAvailable()) {
                throw ValidationException::withMessages([
                    'invite_link' => 'This invite link is no longer available.',
                ]);
            }

            $space->addMember($user, SpaceRole::Member);
            $inviteLink->forceFill([
                'uses_count' => $inviteLink->uses_count + 1,
            ])->save();

            $this->record(
                $space,
                $user,
                SpaceAuditAction::InviteLinkAccepted,
                $user,
                context: ['invite_link_id' => $inviteLink->getKey()],
            );

            return $space;
        });
    }

    public function revoke(Space $space, SpaceInviteLink $inviteLink, User $actor): void
    {
        DB::transaction(function () use ($space, $inviteLink, $actor): void {
            $lockedSpace = Space::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();
            $lockedInviteLink = SpaceInviteLink::query()
                ->whereKey($inviteLink->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $canManage = in_array($lockedSpace->roleFor($actor), [SpaceRole::Owner, SpaceRole::Moderator], true);

            if ($lockedInviteLink->space_id !== $lockedSpace->getKey() || ! $canManage) {
                throw new AuthorizationException('You cannot revoke this invite link.');
            }

            if (! $lockedInviteLink->isAvailable()) {
                throw ValidationException::withMessages([
                    'invite_link' => 'This invite link is no longer available.',
                ]);
            }

            $lockedInviteLink->forceFill(['revoked_at' => now()])->save();

            $this->record(
                $lockedSpace,
                $actor,
                SpaceAuditAction::InviteLinkRevoked,
                context: ['invite_link_id' => $lockedInviteLink->getKey()],
            );
        });
    }

    /** @param array<string, mixed>|null $context */
    private function record(
        Space $space,
        User $actor,
        SpaceAuditAction $action,
        ?User $subject = null,
        ?array $context = null,
    ): void {
        SpaceAuditLog::query()->create([
            'space_id' => $space->getKey(),
            'actor_id' => $actor->getKey(),
            'subject_user_id' => $subject?->getKey(),
            'action' => $action,
            'context' => $context,
        ]);
    }
}
