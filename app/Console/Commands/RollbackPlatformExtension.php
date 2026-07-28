<?php

namespace App\Console\Commands;

use App\Platform\Extensions\ExtensionMigrationException;
use App\Platform\Extensions\ExtensionMigrationExecutor;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RollbackPlatformExtension extends Command
{
    use ConfirmableTrait;

    protected $signature = 'platform:extensions:rollback
        {extension : Reviewed extension id to roll back}
        {--backup-confirmed : Confirm that a current external database backup was verified}
        {--force : Force the operation to run in production}';

    protected $description = "Roll back one disabled extension's latest migration batch";

    public function handle(ExtensionMigrationExecutor $executor): int
    {
        if (! $this->option('backup-confirmed')) {
            $this->components->error('Verify an external database backup, then pass --backup-confirmed.');

            return self::FAILURE;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $id = (string) $this->argument('extension');
        $lock = Cache::lock('platform-extension-migrations', 600);

        if (! $lock->get()) {
            $this->components->error('Another extension migration operation is already running.');

            return self::FAILURE;
        }

        try {
            $rolledBack = $executor->rollback($id, $this->output);

            $rolledBack === []
                ? $this->components->info("{$id}: no applied migration batch to roll back.")
                : $this->components->info("{$id}: rolled back ".count($rolledBack).' migration(s).');

            return self::SUCCESS;
        } catch (ExtensionMigrationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());
            $this->components->warn('No automatic restore was attempted. Inspect the database and use the verified backup when required.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
