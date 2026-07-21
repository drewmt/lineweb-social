<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Space */
class SpaceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Space $space */
        $space = $this->resource;
        /** @var User $viewer */
        $viewer = $request->user();
        $role = $this->viewerRole($space, $viewer);

        return [
            'slug' => $space->slug,
            'name' => $space->name,
            'description' => $space->description,
            'visibility' => $space->visibility->value,
            'member_count' => (int) ($space->members_count ?? 0),
            'viewer' => [
                'is_member' => (bool) ($space->is_member ?? $space->hasMember($viewer)),
                'role' => $role?->value,
                'can_manage' => in_array($role, [SpaceRole::Owner, SpaceRole::Moderator], true),
            ],
        ];
    }

    private function viewerRole(Space $space, User $viewer): ?SpaceRole
    {
        $role = $space->current_role ?? null;

        if (is_string($role)) {
            return SpaceRole::tryFrom($role);
        }

        return $space->roleFor($viewer);
    }
}
