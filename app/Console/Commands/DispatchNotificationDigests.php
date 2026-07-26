<?php

namespace App\Console\Commands;

use App\Enums\NotificationDigestFrequency;
use App\Jobs\SendDailyNotificationDigest;
use App\Models\NotificationPreference;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DispatchNotificationDigests extends Command
{
    protected $signature = 'notifications:dispatch-digests';

    protected $description = 'Queue privacy-safe daily notification digests for eligible members';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->startOfMinute();
        $queued = 0;

        NotificationPreference::query()
            ->where('email_digest_frequency', NotificationDigestFrequency::Daily->value)
            ->whereHas('user', fn ($users) => $users
                ->whereNotNull('email_verified_at')
                ->whereNull('suspended_at'))
            ->orderBy('user_id')
            ->chunkById(
                200,
                function ($preferences) use ($cutoff, &$queued): void {
                    foreach ($preferences as $preference) {
                        SendDailyNotificationDigest::dispatch(
                            $preference->user_id,
                            $cutoff,
                        );
                        $queued++;
                    }
                },
                'user_id',
                'user_id',
            );

        $this->info("Queued {$queued} notification digest job(s).");

        return self::SUCCESS;
    }
}
