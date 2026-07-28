<?php

namespace App\Console\Commands;

use App\Platform\Extensions\ExtensionActivator;
use App\Platform\Extensions\ExtensionAssetPlan;
use App\Platform\Extensions\ExtensionAssetPlanner;
use App\Platform\Extensions\ExtensionInspection;
use App\Platform\Extensions\ExtensionInspector;
use App\Platform\Extensions\ExtensionManifest;
use App\Platform\Extensions\ExtensionMigrationPlan;
use App\Platform\Extensions\ExtensionMigrationPlanner;
use Illuminate\Console\Command;

class InspectPlatformExtensions extends Command
{
    protected $signature = 'platform:extensions {--json : Emit machine-readable inspection results}';

    protected $description = 'Inspect local Lineweb Social extension manifests and core compatibility';

    public function handle(
        ExtensionInspector $inspector,
        ExtensionActivator $activator,
        ExtensionMigrationPlanner $migrationPlanner,
        ExtensionAssetPlanner $assetPlanner,
    ): int {
        $coreVersion = (string) config('extensions.core_version');
        $inspections = $inspector->inspect();
        $enabledIds = $activator->enabledIds();
        $retainedExtensionIds = $migrationPlanner->retainedExtensionIds($inspections);
        $results = array_map(
            static function (ExtensionInspection $inspection) use ($enabledIds, $migrationPlanner, $assetPlanner): array {
                $plan = $inspection->manifest instanceof ExtensionManifest
                    ? $migrationPlanner->plan($inspection)
                    : null;
                $assetPlan = $inspection->manifest instanceof ExtensionManifest
                    ? $assetPlanner->plan($inspection)
                    : null;

                return [
                    ...$inspection->toArray(),
                    'active' => in_array($inspection->manifest?->id, $enabledIds, true),
                    'migrations' => $plan instanceof ExtensionMigrationPlan
                        ? $plan->toArray()
                        : null,
                    'assetPlan' => $assetPlan instanceof ExtensionAssetPlan
                        ? $assetPlan->toArray()
                        : null,
                ];
            },
            $inspections,
        );
        $ready = collect($inspections)->every->isCompatible()
            && collect($results)->every(
                static fn (array $result): bool => ($result['migrations']['status'] ?? null) !== ExtensionMigrationPlan::STATUS_BLOCKED
                    && ($result['assetPlan']['status'] ?? null) !== ExtensionAssetPlan::STATUS_BLOCKED
                    && (! $result['active'] || in_array(
                        $result['migrations']['status'] ?? ExtensionMigrationPlan::STATUS_NONE,
                        [ExtensionMigrationPlan::STATUS_APPLIED, ExtensionMigrationPlan::STATUS_NONE],
                        true,
                    ))
                    && (! $result['active'] || in_array(
                        $result['assetPlan']['status'] ?? ExtensionAssetPlan::STATUS_NONE,
                        [ExtensionAssetPlan::STATUS_PUBLISHED, ExtensionAssetPlan::STATUS_NONE],
                        true,
                    )),
            );

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'coreVersion' => $coreVersion,
                'ready' => $ready,
                'enabled' => $enabledIds,
                'retainedData' => $retainedExtensionIds,
                'extensions' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info("Lineweb Social {$coreVersion}");

            if ($results === []) {
                $this->components->warn('No local extension manifests were found.');
            } else {
                $this->table(
                    ['Extension', 'Version', 'Core constraint', 'Status', 'Database', 'Assets', 'Activation'],
                    array_map(static fn (array $result): array => [
                        $result['name'],
                        $result['version'] ?? '—',
                        $result['core'] ?? '—',
                        $result['status'],
                        $result['migrations']['status'] ?? 'unavailable',
                        $result['assetPlan']['status'] ?? 'unavailable',
                        $result['active'] ? 'active' : 'inactive',
                    ], $results),
                );

                foreach ($inspections as $inspection) {
                    if (! $inspection->isCompatible()) {
                        $this->components->error("{$inspection->directory}: {$inspection->message}");
                    }
                }

                foreach ($results as $result) {
                    if (($result['migrations']['status'] ?? null) === ExtensionMigrationPlan::STATUS_BLOCKED) {
                        $this->components->error("{$result['name']}: {$result['migrations']['message']}");
                    }

                    if (($result['assetPlan']['status'] ?? null) === ExtensionAssetPlan::STATUS_BLOCKED) {
                        $this->components->error("{$result['name']}: {$result['assetPlan']['message']}");
                    }
                }
            }

            if ($retainedExtensionIds !== []) {
                $this->components->warn(
                    'Retained migration ownership records: '.implode(', ', $retainedExtensionIds),
                );
            }
        }

        return $ready
            ? self::SUCCESS
            : self::FAILURE;
    }
}
