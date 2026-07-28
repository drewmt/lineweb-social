<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Extensions\ExtensionActivator;
use App\Platform\Extensions\ExtensionAssetPlan;
use App\Platform\Extensions\ExtensionAssetPlanner;
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
        ExtensionAssetPlanner $assetPlanner,
    ): Response {
        $inspections = $inspector->inspect();
        $enabledIds = $activator->enabledIds();
        $migrationPlans = collect($inspections)
            ->filter(fn (ExtensionInspection $inspection): bool => $inspection->manifest instanceof ExtensionManifest)
            ->mapWithKeys(fn (ExtensionInspection $inspection): array => [
                $inspection->manifest->id => $migrationPlanner->plan($inspection),
            ]);
        $retainedExtensionIds = $migrationPlanner->retainedExtensionIds($inspections);
        $assetPlans = collect($inspections)
            ->filter(fn (ExtensionInspection $inspection): bool => $inspection->manifest instanceof ExtensionManifest)
            ->mapWithKeys(fn (ExtensionInspection $inspection): array => [
                $inspection->manifest->id => $assetPlanner->plan($inspection),
            ]);

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
                'assetsPublished' => $assetPlans
                    ->where('status', ExtensionAssetPlan::STATUS_PUBLISHED)
                    ->sum(fn (ExtensionAssetPlan $plan): int => count($plan->publishedAssets)),
                'assetsAttention' => $assetPlans
                    ->filter(fn (ExtensionAssetPlan $plan): bool => in_array(
                        $plan->status,
                        [ExtensionAssetPlan::STATUS_UNPUBLISHED, ExtensionAssetPlan::STATUS_BLOCKED],
                        true,
                    ))
                    ->count(),
            ],
            'retainedExtensionIds' => $retainedExtensionIds,
            'extensions' => array_map(
                static function (ExtensionInspection $inspection) use ($enabledIds, $migrationPlans, $assetPlans): array {
                    $plan = $inspection->manifest instanceof ExtensionManifest
                        ? $migrationPlans->get($inspection->manifest->id)
                        : null;
                    $assetPlan = $inspection->manifest instanceof ExtensionManifest
                        ? $assetPlans->get($inspection->manifest->id)
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
            ),
        ]);
    }
}
