<?php

namespace App\Community;

use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateSpace
{
    public function handle(
        User $owner,
        string $name,
        ?string $description,
        SpaceVisibility $visibility,
    ): Space {
        return DB::transaction(function () use ($owner, $name, $description, $visibility): Space {
            $baseSlug = Str::limit(Str::slug($name) ?: 'space', 110, '');
            $slug = $baseSlug;
            $suffix = 2;

            while (Space::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix;
                $suffix++;
            }

            return Space::query()->create([
                'owner_id' => $owner->getKey(),
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'visibility' => $visibility,
            ]);
        });
    }
}
