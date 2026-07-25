<?php

namespace App\Console\Commands;

use App\Enums\ReportStatus;
use App\Models\DirectMessageReport;
use Illuminate\Console\Command;

class PruneDirectMessageReports extends Command
{
    protected $signature = 'message-reports:prune
        {--days= : Delete closed report evidence reviewed before this many days (default: 180)}';

    protected $description = 'Prune closed direct-message report evidence after its retention window';

    public function handle(): int
    {
        $days = filter_var(
            $this->option('days') ?? DirectMessageReport::RETENTION_DAYS,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 30, 'max_range' => 3650]],
        );

        if ($days === false) {
            $this->error('The retention window must be between 30 and 3650 days.');

            return self::FAILURE;
        }

        $deleted = DirectMessageReport::query()
            ->whereIn('status', [
                ReportStatus::Resolved,
                ReportStatus::Dismissed,
            ])
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} closed direct-message report(s).");

        return self::SUCCESS;
    }
}
