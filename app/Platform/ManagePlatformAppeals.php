<?php

namespace App\Platform;

use App\Enums\PlatformAppealAction;
use App\Enums\PlatformAppealStatus;
use App\Enums\PlatformAuditAction;
use App\Models\PlatformAppeal;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManagePlatformAppeals
{
    public function submit(User $user, string $statement): PlatformAppeal
    {
        return DB::transaction(function () use ($user, $statement): PlatformAppeal {
            $lockedUser = User::query()
                ->whereKey($user)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedUser->isSuspended() || $lockedUser->suspended_at === null) {
                throw ValidationException::withMessages([
                    'statement' => 'Only a restricted account can submit an appeal.',
                ]);
            }

            if ($lockedUser->suspension_reference === null) {
                $lockedUser->forceFill([
                    'suspension_reference' => (string) Str::uuid(),
                ])->save();
            }

            $existingAppeal = PlatformAppeal::query()
                ->where('suspension_reference', $lockedUser->suspension_reference)
                ->lockForUpdate()
                ->first();

            if ($existingAppeal instanceof PlatformAppeal) {
                throw ValidationException::withMessages([
                    'statement' => 'An appeal has already been submitted for this restriction.',
                ]);
            }

            $appeal = PlatformAppeal::query()->create([
                'user_id' => $lockedUser->getKey(),
                'suspension_reference' => $lockedUser->suspension_reference,
                'suspension_started_at' => $lockedUser->suspended_at,
                'status' => PlatformAppealStatus::Open,
                'statement' => $statement,
            ]);

            $this->record(
                PlatformAuditAction::AppealSubmitted,
                $lockedUser,
                $lockedUser,
                context: ['appeal_id' => $appeal->getKey()],
            );

            return $appeal;
        }, 3);
    }

    public function moderate(
        PlatformAppeal $appeal,
        User $administrator,
        PlatformAppealAction $action,
        string $decisionMessage,
    ): PlatformAppeal {
        return DB::transaction(function () use (
            $appeal,
            $administrator,
            $action,
            $decisionMessage,
        ): PlatformAppeal {
            $appealUserId = PlatformAppeal::query()
                ->whereKey($appeal)
                ->value('user_id');
            abort_unless(is_int($appealUserId), 404);

            [$lockedAdministrator, $lockedUser] = $this->lockPair(
                $administrator,
                $appealUserId,
            );
            $lockedAppeal = PlatformAppeal::query()
                ->whereKey($appeal)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedAdministrator->isAdministrator()
                || $lockedAdministrator->isSuspended()) {
                throw new AuthorizationException;
            }

            if ($lockedAppeal->user_id !== $lockedUser->getKey()
                || ! $lockedUser->isSuspended()
                || $lockedUser->suspended_at === null
                || $lockedUser->suspension_reference === null
                || $lockedAppeal->suspension_reference
                    !== $lockedUser->suspension_reference) {
                throw ValidationException::withMessages([
                    'action' => 'This appeal no longer matches an active account restriction.',
                ]);
            }

            $nextStatus = $this->nextStatus($lockedAppeal->status, $action);
            $lockedAppeal->forceFill([
                'status' => $nextStatus,
                'decision_message' => $decisionMessage,
                'reviewed_by' => $lockedAdministrator->getKey(),
                'reviewed_at' => now(),
            ])->save();

            if ($action === PlatformAppealAction::Approve) {
                $lockedUser->forceFill([
                    'suspended_at' => null,
                    'suspension_reference' => null,
                    'suspension_reason' => null,
                    'suspended_by' => null,
                ])->save();
            }

            $this->record(
                match ($action) {
                    PlatformAppealAction::Review => PlatformAuditAction::AppealReviewStarted,
                    PlatformAppealAction::Approve => PlatformAuditAction::AppealApproved,
                    PlatformAppealAction::Deny => PlatformAuditAction::AppealDenied,
                },
                $lockedUser,
                $lockedAdministrator,
                $decisionMessage,
                ['appeal_id' => $lockedAppeal->getKey()],
            );

            if ($action === PlatformAppealAction::Approve) {
                $this->record(
                    PlatformAuditAction::MemberReinstated,
                    $lockedUser,
                    $lockedAdministrator,
                    'Access restored through an approved account appeal.',
                    ['appeal_id' => $lockedAppeal->getKey()],
                );
            }

            return $lockedAppeal->refresh();
        }, 3);
    }

    private function nextStatus(
        PlatformAppealStatus $current,
        PlatformAppealAction $action,
    ): PlatformAppealStatus {
        if ($action === PlatformAppealAction::Review
            && $current !== PlatformAppealStatus::Open) {
            $this->invalidTransition('Only a newly submitted appeal can be moved into review.');
        }

        if (in_array($action, [
            PlatformAppealAction::Approve,
            PlatformAppealAction::Deny,
        ], true) && ! $current->isActive()) {
            $this->invalidTransition('This appeal already has a final decision.');
        }

        return match ($action) {
            PlatformAppealAction::Review => PlatformAppealStatus::Reviewing,
            PlatformAppealAction::Approve => PlatformAppealStatus::Approved,
            PlatformAppealAction::Deny => PlatformAppealStatus::Denied,
        };
    }

    /**
     * @return array{User, User}
     */
    private function lockPair(User $administrator, int $userId): array
    {
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->whereKey([$administrator->getKey(), $userId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lockedAdministrator = $users->get($administrator->getKey());
        $lockedUser = $users->get($userId);

        abort_unless(
            $lockedAdministrator instanceof User && $lockedUser instanceof User,
            404,
        );

        return [$lockedAdministrator, $lockedUser];
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

    private function invalidTransition(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
