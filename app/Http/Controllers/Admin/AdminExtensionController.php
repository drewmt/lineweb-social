<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Extensions\ExtensionActivator;
use App\Platform\Extensions\ExtensionInspection;
use App\Platform\Extensions\ExtensionInspector;
use App\Platform\Extensions\ExtensionManifest;
use App\Platform\Extensions\ExtensionMigrationPlan;
use App\Platform\Extensions\ExtensionMigrationPlanner;
use Inertia\Inertia;
use Inertia\Response;

class AdminExtensionController extends Controller
{
    public function __invoke(
        ExtensionInspector $inspector,
        ExtensionActivator $activator,
        ExtensionMigrationPlanner $migrationPlanner,
    ): Response {
        $inspections = $inspector->inspect();
        $enabledIds = $activator->enabledIds();
        $migrationPlans = collect($inspections)
            ->filter(fn (ExtensionInspection $inspection): bool => $inspection->manifest instanceof ExtensionManifest)
            ->mapWithKeys(fn (ExtensionInspection $inspection): array => [
                $inspection->manifest->id => $migrationPlanner->plan($inspection),
            ]);
        $retainedExtensionIds = $migrationPlanner->retainedExtensionIds($inspections);

        return Inertia::render('admin/extensions', [
            'coreVersion' => (string) config('extensions.core_version'),
            'summary' => [
                'discovered' => count($inspections),
                'active' => count($enabledIds),
                'compatible' => collect($inspections)->filter->isCompatible()->count(),
                'actionRequired' => collect($inspections)->reject->isCompatible()->count(),
                'migrationPending' => $migrationPlans
                    ->where('status', ExtensionMigrationPlan::STATUS_PENDING)
                    ->sum(fn (ExtensionMigrationPlan $plan): int => count($plan->pendingFiles())),
                'migrationBlocked' => $migrationPlans
                    ->where('status', ExtensionMigrationPlan::STATUS_BLOCKED)
                    ->count(),
                'retainedData' => count($retainedExtensionIds),
            ],
            'retainedExtensionIds' => $retainedExtensionIds,
            'extensions' => array_map(
                static function (ExtensionInspection $inspection) use ($enabledIds, $migrationPlans): array {
                    $plan = $inspection->manifest instanceof ExtensionManifest
                        ? $migrationPlans->get($inspection->manifest->id)
                        : null;

                    return [
                        ...$inspection->toArray(),
                        'active' => in_array($inspection->manifest?->id, $enabledIds, true),
                        'migrations' => $plan instanceof ExtensionMigrationPlan
                            ? $plan->toArray()
                            : null,
                    ];
                },
                $inspections,
            ),
        ]);
    }
}
