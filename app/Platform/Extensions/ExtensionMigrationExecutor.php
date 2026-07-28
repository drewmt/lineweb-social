<?php

namespace App\Platform\Extensions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;

final class ExtensionMigrationExecutor
{
    public function __construct(
        private readonly ExtensionInspector $inspector,
        private readonly ExtensionMigrationPlanner $planner,
        private readonly ExtensionActivator $activator,
        private readonly DatabaseManager $resolver,
        private readonly Filesystem $files,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  list<string>  $ids
     * @return array<string, list<string>>
     */
    public function migrate(array $ids, OutputInterface $output): array
    {
        $plans = $this->preflight($ids);
        $ran = [];

        foreach ($plans as $id => $plan) {
            if ($plan->migrationDirectory === null || $plan->pendingFiles() === []) {
                $ran[$id] = [];

                continue;
            }

            $migrator = $this->migrator($plan);
            $migrator->setOutput($output);

            $ran[$id] = array_values(array_map(
                static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
                $migrator->run($plan->migrationDirectory),
            ));
        }

        return $ran;
    }

    /** @return list<string> */
    public function rollback(string $id, OutputInterface $output): array
    {
        $plan = $this->preflight([$id])[$id];

        if ($plan->migrationDirectory === null) {
            return [];
        }

        $migrator = $this->migrator($plan);
        $migrator->setOutput($output);

        return array_values(array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            $migrator->rollback($plan->migrationDirectory),
        ));
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, ExtensionMigrationPlan>
     */
    private function preflight(array $ids): array
    {
        if ($ids === [] || count($ids) !== count(array_unique($ids))) {
            throw new ExtensionMigrationException('Select one or more unique extension ids.');
        }

        $inspections = $this->inspector->inspect();
        $byId = [];

        foreach ($inspections as $inspection) {
            if ($inspection->manifest instanceof ExtensionManifest) {
                $byId[$inspection->manifest->id] = $inspection;
            }
        }

        $plans = [];

        foreach ($ids as $id) {
            $inspection = $byId[$id] ?? null;

            if (! $inspection instanceof ExtensionInspection) {
                throw new ExtensionMigrationException("Extension '{$id}' was not found.");
            }

            if (! $inspection->isCompatible()) {
                throw new ExtensionMigrationException(
                    "Extension '{$id}' cannot migrate: {$inspection->message}",
                );
            }

            if ($this->activator->isEnabled($id)) {
                throw new ExtensionMigrationException(
                    "Disable extension '{$id}' before changing its database schema.",
                );
            }

            $plan = $this->planner->plan($inspection);

            if ($plan->status === ExtensionMigrationPlan::STATUS_BLOCKED) {
                throw new ExtensionMigrationException(
                    "Extension '{$id}' cannot migrate: {$plan->message} ".implode(' ', $plan->problems),
                );
            }

            $plans[$id] = $plan;
        }

        return $plans;
    }

    private function migrator(ExtensionMigrationPlan $plan): Migrator
    {
        $checksums = [];

        foreach ($plan->files as $file) {
            $checksums[$file->name] = $file->checksum;
        }

        return new Migrator(
            new ExtensionMigrationRepository(
                $this->resolver,
                $plan->manifest->id,
                $plan->manifest->version,
                $checksums,
            ),
            $this->resolver,
            $this->files,
            $this->events,
        );
    }
}
