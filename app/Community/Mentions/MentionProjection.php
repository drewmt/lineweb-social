<?php

namespace App\Community\Mentions;

use App\Models\User;

final class MentionProjection
{
    public function __construct(private readonly MentionParser $parser) {}

    /**
     * Resolve all profiles that the current viewer may open in one query.
     *
     * @param  iterable<string>  $bodies
     * @return array<string, array{handle: string, name: string, url: string}>
     */
    public function resolve(User $viewer, iterable $bodies): array
    {
        $handles = [];

        foreach ($bodies as $body) {
            foreach ($this->parser->handles($body) as $handle) {
                $handles[$handle] = true;
            }
        }

        if ($handles === []) {
            return [];
        }

        return User::query()
            ->visibleTo($viewer)
            ->whereIn('handle', array_keys($handles))
            ->get(['id', 'name', 'handle'])
            ->mapWithKeys(fn (User $user): array => [
                $user->handle => [
                    'handle' => $user->handle,
                    'name' => $user->name,
                    'url' => route('people.show', $user),
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, array{handle: string, name: string, url: string}>  $resolved
     * @return list<array{handle: string, name: string, url: string}>
     */
    public function forBody(string $body, array $resolved): array
    {
        $mentions = [];

        foreach ($this->parser->handles($body) as $handle) {
            if (isset($resolved[$handle])) {
                $mentions[] = $resolved[$handle];
            }
        }

        return $mentions;
    }
}
