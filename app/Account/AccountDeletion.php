<?php

namespace App\Account;

use App\Enums\PlatformRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountDeletion
{
    /**
     * Owned Spaces that contain another person's membership or content.
     *
     * @return Collection<int, Space>
     */
    public function blockersFor(User $user): Collection
    {
        return $this->blockingSpacesQuery($user)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->isAdministrator()) {
                $administrators = User::query()
                    ->where('platform_role', PlatformRole::Administrator)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                if ($administrators->count() === 1) {
                    throw ValidationException::withMessages([
                        'account' => 'Grant another platform administrator before deleting this account.',
                    ]);
                }
            }

            Space::query()
                ->where('owner_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->get(['id']);

            $blockers = $this->blockersFor($lockedUser);

            if ($blockers->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'account' => 'Transfer ownership of Spaces with community activity before deleting your account.',
                ]);
            }

            $lockedUser->delete();
        }, 3);
    }

    /** @return Builder<Space> */
    private function blockingSpacesQuery(User $user): Builder
    {
        return Space::query()
            ->where('owner_id', $user->getKey())
            ->where(function (Builder $spaces) use ($user): void {
                $spaces
                    ->whereHas('members', fn (Builder $members) => $members
                        ->whereKeyNot($user->getKey()))
                    ->orWhereHas('posts', fn (Builder $posts) => $posts
                        ->where('user_id', '!=', $user->getKey()))
                    ->orWhereHas('posts.comments', fn (Builder $comments) => $comments
                        ->where('user_id', '!=', $user->getKey()))
                    ->orWhereHas('invitations', fn (Builder $invitations) => $invitations
                        ->where('invited_by', '!=', $user->getKey()))
                    ->orWhereHas('auditLogs', fn (Builder $auditLogs) => $auditLogs
                        ->where(function (Builder $people) use ($user): void {
                            $people
                                ->where('actor_id', '!=', $user->getKey())
                                ->orWhere('subject_user_id', '!=', $user->getKey());
                        }))
                    ->orWhereHas('postReports', fn (Builder $reports) => $reports
                        ->where('reporter_id', '!=', $user->getKey()))
                    ->orWhereHas('commentReports', fn (Builder $reports) => $reports
                        ->where('reporter_id', '!=', $user->getKey()));
            });
    }
}
