<?php

namespace App\Community;

use App\Enums\SpaceAuditAction;
use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProvisionStarterCommunity
{
    public const BLUEPRINT = 'community-v1';

    private const DESCRIPTION = 'A private starting point for welcoming members, sharing ideas, and shaping the community together.';

    /** @var list<string> */
    private const POSTS = [
        'Welcome to the community. Start here: share why this space exists, who it serves, and the behaviours that will keep it useful.',
        'Introduce yourself: what are you working on, what can you help with, and what would you like to learn from other members?',
        'Shape the roadmap: share one problem the community should solve next, then use reactions and comments to surface the strongest ideas.',
    ];

    public function __construct(
        private readonly CreateSpace $spaces,
        private readonly PublishPost $posts,
        private readonly ManageSpaceHighlights $highlights,
    ) {}

    public function handle(
        User $owner,
        string $name,
        SpaceVisibility $visibility = SpaceVisibility::Private,
    ): StarterCommunityResult {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Give the starter community a name.',
            ]);
        }

        if (mb_strlen($name) > 120) {
            throw ValidationException::withMessages([
                'name' => 'Starter community names can be up to 120 characters.',
            ]);
        }

        return DB::transaction(function () use ($owner, $name, $visibility): StarterCommunityResult {
            $lockedOwner = User::query()
                ->whereKey($owner->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedOwner->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'email' => 'The member must verify their email before receiving a starter community.',
                ]);
            }

            if ($lockedOwner->isSuspended()) {
                throw ValidationException::withMessages([
                    'account' => 'A suspended member cannot receive a starter community.',
                ]);
            }

            $existing = SpaceAuditLog::query()
                ->with('space')
                ->where('actor_id', $lockedOwner->getKey())
                ->where('action', SpaceAuditAction::StarterProvisioned->value)
                ->where('context->blueprint', self::BLUEPRINT)
                ->oldest('id')
                ->first();

            if ($existing?->space instanceof Space) {
                return new StarterCommunityResult($existing->space, false);
            }

            $space = $this->spaces->handle(
                $lockedOwner,
                $name,
                self::DESCRIPTION,
                $visibility,
            );

            $createdPosts = [];

            foreach (self::POSTS as $body) {
                $createdPosts[] = $this->posts->publish(
                    $lockedOwner,
                    $space,
                    $body,
                    [],
                    [],
                );
            }

            $this->highlights->highlight($lockedOwner, $space, $createdPosts[0]);

            SpaceAuditLog::query()->create([
                'space_id' => $space->getKey(),
                'actor_id' => $lockedOwner->getKey(),
                'action' => SpaceAuditAction::StarterProvisioned,
                'context' => [
                    'blueprint' => self::BLUEPRINT,
                    'post_ids' => array_map(
                        static fn ($post): int => $post->getKey(),
                        $createdPosts,
                    ),
                ],
            ]);

            return new StarterCommunityResult($space, true);
        });
    }
}
