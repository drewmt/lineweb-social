<?php

namespace App\Console\Commands;

use App\Community\ProvisionStarterCommunity as StarterCommunityProvisioner;
use App\Enums\SpaceVisibility;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ProvisionStarterCommunity extends Command
{
    protected $signature = 'platform:starter-community
        {email : The existing verified member email address}
        {--name=Community HQ : The starter Space name}
        {--visibility=private : public, private, or hidden}
        {--confirm : Confirm the database writes}';

    protected $description = 'Create an idempotent starter community for an existing member';

    public function handle(StarterCommunityProvisioner $provisioner): int
    {
        if (! $this->option('confirm')) {
            $this->error('Pass --confirm after reviewing the member, name, and visibility.');

            return CommandStatus::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));
        $member = User::query()->where('email', $email)->first();

        if (! $member instanceof User) {
            $this->error('No member exists with that email address.');

            return CommandStatus::FAILURE;
        }

        $visibility = SpaceVisibility::tryFrom(
            Str::lower(trim((string) $this->option('visibility'))),
        );

        if (! $visibility instanceof SpaceVisibility) {
            $this->error('Visibility must be public, private, or hidden.');

            return CommandStatus::FAILURE;
        }

        try {
            $result = $provisioner->handle(
                $member,
                (string) $this->option('name'),
                $visibility,
            );
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return CommandStatus::FAILURE;
        }

        $url = route('spaces.show', $result->space);

        $this->info($result->created
            ? "Starter community created: {$result->space->name} ({$url})"
            : "Starter community already exists: {$result->space->name} ({$url})");

        return CommandStatus::SUCCESS;
    }
}
