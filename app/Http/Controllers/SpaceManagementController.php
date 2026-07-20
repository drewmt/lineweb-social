<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\SpaceInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SpaceManagementController extends Controller
{
    public function __invoke(Request $request, Space $space): Response
    {
        Gate::authorize('moderate', $space);

        /** @var User $actor */
        $actor = $request->user();
        $actorRole = $space->roleFor($actor);

        $members = $space->members()
            ->select(['users.id', 'users.name'])
            ->orderBy('users.name')
            ->get()
            ->sortBy(function (User $member): array {
                $role = $this->membershipRole($member);

                return [match ($role) {
                    SpaceRole::Owner => 0,
                    SpaceRole::Moderator => 1,
                    SpaceRole::Member => 2,
                }, Str::lower($member->name)];
            })
            ->values()
            ->map(function (User $member) use ($actor, $actorRole, $space): array {
                $role = $this->membershipRole($member);

                return [
                    'id' => $member->getKey(),
                    'name' => $member->name,
                    'role' => $role->value,
                    'canChangeRole' => $actorRole === SpaceRole::Owner && $role !== SpaceRole::Owner,
                    'canRemove' => Gate::forUser($actor)->allows('removeMember', [$space, $member]),
                    'canReceiveOwnership' => $actorRole === SpaceRole::Owner && $member->getKey() !== $actor->getKey(),
                ];
            })
            ->all();

        $invitations = $space->invitations()
            ->with('inviter:id,name')
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get()
            ->map(fn (SpaceInvitation $invitation): array => [
                'id' => $invitation->getKey(),
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'inviter' => $invitation->invited_by !== null ? $invitation->inviter->name : null,
                'expiresAt' => $invitation->expires_at->toIso8601String(),
                'canCancel' => Gate::forUser($actor)->allows('revokeInvitation', [$space, $invitation]),
            ])
            ->all();

        $audit = $space->auditLogs()
            ->with(['actor:id,name', 'subject:id,name'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (SpaceAuditLog $log): array => [
                'id' => $log->getKey(),
                'action' => $log->action->value,
                'actor' => $log->actor_id !== null ? $log->actor->name : 'Former member',
                'subject' => $log->subject_user_id !== null ? $log->subject->name : null,
                'reason' => $log->reason,
                'context' => $log->context,
                'createdAt' => $log->created_at->toIso8601String(),
            ])
            ->all();

        return Inertia::render('spaces/manage', [
            'space' => [
                'name' => $space->name,
                'slug' => $space->slug,
                'description' => $space->description,
            ],
            'members' => $members,
            'invitations' => $invitations,
            'audit' => $audit,
            'permissions' => [
                'canInviteModerators' => $actorRole === SpaceRole::Owner,
                'canTransferOwnership' => $actorRole === SpaceRole::Owner,
            ],
            'openReportsCount' => $space->postReports()
                ->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])
                ->count()
                + $space->commentReports()
                    ->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])
                    ->count(),
        ]);
    }

    private function membershipRole(User $member): SpaceRole
    {
        $pivot = $member->getRelation('pivot');

        if (! $pivot instanceof Model) {
            throw new \LogicException('The membership pivot must be loaded.');
        }

        return SpaceRole::from((string) $pivot->getAttribute('role'));
    }
}
