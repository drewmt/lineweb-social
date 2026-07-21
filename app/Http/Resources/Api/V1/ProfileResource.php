<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $profile */
        $profile = $this->resource;

        return [
            'handle' => $profile->handle,
            'name' => $profile->name,
            'headline' => $profile->headline,
            'bio' => $profile->bio,
            'location' => $profile->location,
            'website_url' => $profile->website_url,
            'member_since' => $profile->created_at?->toDateString(),
            'viewer' => [
                'is_self' => true,
                'is_muted' => false,
            ],
        ];
    }
}
