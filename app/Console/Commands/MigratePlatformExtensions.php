<?php

namespace App\Console\Commands;

use App\Platform\Extensions\ExtensionMigrationException;
use App\Platform\Extensions\ExtensionMigrationExecutor;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MigratePlatformExtensions extends Command
{
    use ConfirmableTrait;

    protected $signature = 'platform:extensions:migrate
        {extension* : Reviewed extension ids to migrate}
        {--backup-confirmed : Confirm that a current external database backup was verified}
        {--force : Force the operation to run in production}';

    protected $description = 'Run pending database migrations for reviewed local extensions';

    public function handle(ExtensionMigrationExecutor $executor): int
    {
        if (! $this->option('backup-confirmed')) {
            $this->components->error('Verify an external database backup, then pass --backup-confirmed.');

            return self::FAILURE;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        /** @var list<string> $ids */
        $ids = array_values($this->argument('extension'));
        $lock = Cache::lock('platform-extension-migrations', 600);

        if (! $lock->get()) {
            $this->components->error('Another extension migration operation is already running.');

            return self::FAILURE;
        }

        try {
            $ran = $executor->migrate($ids, $this->output);

            foreach ($ran as $id => $migrations) {
                $migrations === []
                    ? $this->components->info("{$id}: no pending migrations.")
                    : $this->components->info("{$id}: applied ".count($migrations).' migration(s).');
            }

            return self::SUCCESS;
        } catch (ExtensionMigrationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());
            $this->components->warn('No automatic rollback was attempted. Inspect the database and restore the verified backup when required.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
