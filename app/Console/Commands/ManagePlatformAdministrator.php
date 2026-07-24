<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Platform\PlatformAdministration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ManagePlatformAdministrator extends Command
{
    protected $signature = 'platform:administrator
        {email : The existing member email address}
        {--revoke : Remove administrator access}';

    protected $description = 'Grant or revoke Lineweb Social platform administrator access';

    public function handle(PlatformAdministration $administration): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $member = User::query()->where('email', $email)->first();

        if (! $member instanceof User) {
            $this->error('No member exists with that email address.');

            return CommandStatus::FAILURE;
        }

        try {
            if ($this->option('revoke')) {
                $changed = $administration->revokeAdministrator($member);
                $this->info($changed
                    ? "Administrator access was revoked for {$member->email}."
                    : "{$member->email} is not an administrator.");

                return CommandStatus::SUCCESS;
            }

            $changed = $administration->grantAdministrator($member);
            $this->info($changed
                ? "Administrator access was granted to {$member->email}."
                : "{$member->email} is already an administrator.");

            return CommandStatus::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());

            return CommandStatus::FAILURE;
        }
    }
}
