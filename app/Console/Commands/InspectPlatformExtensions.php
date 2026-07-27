<?php

namespace App\Console\Commands;

use App\Platform\Extensions\ExtensionActivator;
use App\Platform\Extensions\ExtensionInspection;
use App\Platform\Extensions\ExtensionInspector;
use Illuminate\Console\Command;

class InspectPlatformExtensions extends Command
{
    protected $signature = 'platform:extensions {--json : Emit machine-readable inspection results}';

    protected $description = 'Inspect local Lineweb Social extension manifests and core compatibility';

    public function handle(
        ExtensionInspector $inspector,
        ExtensionActivator $activator,
    ): int {
        $coreVersion = (string) config('extensions.core_version');
        $inspections = $inspector->inspect();
        $enabledIds = $activator->enabledIds();
        $results = array_map(
            static fn (ExtensionInspection $inspection): array => [
                ...$inspection->toArray(),
                'active' => in_array($inspection->manifest?->id, $enabledIds, true),
            ],
            $inspections,
        );

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'coreVersion' => $coreVersion,
                'ready' => collect($inspections)->every->isCompatible(),
                'enabled' => $enabledIds,
                'extensions' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info("Lineweb Social {$coreVersion}");

            if ($results === []) {
                $this->components->warn('No local extension manifests were found.');
            } else {
                $this->table(
                    ['Extension', 'Version', 'Core constraint', 'Status', 'Activation'],
                    array_map(static fn (array $result): array => [
                        $result['name'],
                        $result['version'] ?? '—',
                        $result['core'] ?? '—',
                        $result['status'],
                        $result['active'] ? 'active' : 'inactive',
                    ], $results),
                );

                foreach ($inspections as $inspection) {
                    if (! $inspection->isCompatible()) {
                        $this->components->error("{$inspection->directory}: {$inspection->message}");
                    }
                }
            }
        }

        return collect($inspections)->every->isCompatible()
            ? self::SUCCESS
            : self::FAILURE;
    }
}
