<?php

namespace App\Platform;

use App\Enums\PlatformAppealStatus;
use App\Enums\PlatformAuditAction;
use App\Enums\PlatformRole;
use App\Models\PlatformAppeal;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformAdministration
{
    public function suspend(User $member, User $actor, string $reason): void
    {
        DB::transaction(function () use ($member, $actor, $reason): void {
            [$lockedActor, $lockedMember] = $this->lockPair($actor, $member);
            $this->authorizeAdministrator($lockedActor);

            if ($lockedActor->is($lockedMember)) {
                throw ValidationException::withMessages([
                    'member' => 'You cannot suspend your own administrator account.',
                ]);
            }

            if ($lockedMember->isAdministrator()) {
                throw ValidationException::withMessages([
                    'member' => 'Administrator accounts cannot be suspended from the web interface.',
                ]);
            }

            if ($lockedMember->isSuspended()) {
                throw ValidationException::withMessages([
                    'member' => 'This member is already suspended.',
                ]);
            }

            $lockedMember->forceFill([
                'suspended_at' => now(),
                'suspension_reference' => (string) Str::uuid(),
                'suspension_reason' => $reason,
                'suspended_by' => $lockedActor->getKey(),
                'remember_token' => Str::random(60),
            ])->save();

            $lockedMember->tokens()->delete();
            DB::table('sessions')
                ->where('user_id', $lockedMember->getKey())
                ->delete();

            $this->record(
                PlatformAuditAction::MemberSuspended,
                $lockedMember,
                $lockedActor,
                $reason,
            );
        }, 3);
    }

    public function reinstate(User $member, User $actor, string $reason): void
    {
        DB::transaction(function () use ($member, $actor, $reason): void {
            [$lockedActor, $lockedMember] = $this->lockPair($actor, $member);
            $this->authorizeAdministrator($lockedActor);

            if (! $lockedMember->isSuspended()) {
                throw ValidationException::withMessages([
                    'member' => 'This member is not suspended.',
                ]);
            }

            $suspensionReference = $lockedMember->suspension_reference;
            $appeal = PlatformAppeal::query()
                ->whereBelongsTo($lockedMember)
                ->where('suspension_reference', $suspensionReference)
                ->whereIn('status', [
                    PlatformAppealStatus::Open->value,
                    PlatformAppealStatus::Reviewing->value,
                ])
                ->lockForUpdate()
                ->first();

            $lockedMember->forceFill([
                'suspended_at' => null,
                'suspension_reference' => null,
                'suspension_reason' => null,
                'suspended_by' => null,
            ])->save();

            if ($appeal instanceof PlatformAppeal) {
                $appeal->forceFill([
                    'status' => PlatformAppealStatus::Approved,
                    'decision_message' => 'Your account access was restored after administrator review.',
                    'reviewed_by' => $lockedActor->getKey(),
                    'reviewed_at' => now(),
                ])->save();

                $this->record(
                    PlatformAuditAction::AppealApproved,
                    $lockedMember,
                    $lockedActor,
                    'Account access restored from the member directory.',
                    ['appeal_id' => $appeal->getKey()],
                );
            }

            $this->record(
                PlatformAuditAction::MemberReinstated,
                $lockedMember,
                $lockedActor,
                $reason,
                $appeal instanceof PlatformAppeal
                    ? ['appeal_id' => $appeal->getKey()]
                    : null,
            );
        }, 3);
    }

    public function grantAdministrator(User $member): bool
    {
        return DB::transaction(function () use ($member): bool {
            $lockedMember = User::query()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMember->isAdministrator()) {
                return false;
            }

            $lockedMember->forceFill([
                'platform_role' => PlatformRole::Administrator,
            ])->save();

            $this->record(
                PlatformAuditAction::AdministratorGranted,
                $lockedMember,
            );

            return true;
        }, 3);
    }

    public function revokeAdministrator(User $member): bool
    {
        return DB::transaction(function () use ($member): bool {
            $administrators = User::query()
                ->where('platform_role', PlatformRole::Administrator)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedMember = $administrators->first(
                fn (User $administrator): bool => $administrator->is($member),
            );

            if (! $lockedMember instanceof User) {
                return false;
            }

            if ($administrators->count() === 1) {
                throw ValidationException::withMessages([
                    'administrator' => 'The last platform administrator cannot be revoked.',
                ]);
            }

            $lockedMember->forceFill([
                'platform_role' => PlatformRole::Member,
            ])->save();

            $this->record(
                PlatformAuditAction::AdministratorRevoked,
                $lockedMember,
            );

            return true;
        }, 3);
    }

    /**
     * @return array{User, User}
     */
    private function lockPair(User $actor, User $member): array
    {
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->whereKey([$actor->getKey(), $member->getKey()])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lockedActor = $users->get($actor->getKey());
        $lockedMember = $users->get($member->getKey());

        abort_unless($lockedActor instanceof User && $lockedMember instanceof User, 404);

        return [$lockedActor, $lockedMember];
    }

    private function authorizeAdministrator(User $actor): void
    {
        if (! $actor->isAdministrator() || $actor->isSuspended()) {
            throw new AuthorizationException;
        }
    }

    /** @param array<string, mixed>|null $context */
    private function record(
        PlatformAuditAction $action,
        User $subject,
        ?User $actor = null,
        ?string $reason = null,
        ?array $context = null,
    ): void {
        PlatformAuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'subject_user_id' => $subject->getKey(),
            'action' => $action,
            'reason' => $reason,
            'context' => $context,
        ]);
    }
}
