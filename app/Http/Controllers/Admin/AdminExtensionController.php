<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Extensions\ExtensionActivator;
use App\Platform\Extensions\ExtensionInspection;
use App\Platform\Extensions\ExtensionInspector;
use Inertia\Inertia;
use Inertia\Response;

class AdminExtensionController extends Controller
{
    public function __invoke(
        ExtensionInspector $inspector,
        ExtensionActivator $activator,
    ): Response {
        $inspections = $inspector->inspect();
        $enabledIds = $activator->enabledIds();

        return Inertia::render('admin/extensions', [
            'coreVersion' => (string) config('extensions.core_version'),
            'summary' => [
                'discovered' => count($inspections),
                'active' => count($enabledIds),
                'compatible' => collect($inspections)->filter->isCompatible()->count(),
                'actionRequired' => collect($inspections)->reject->isCompatible()->count(),
            ],
            'extensions' => array_map(
                static fn (ExtensionInspection $inspection): array => [
                    ...$inspection->toArray(),
                    'active' => in_array($inspection->manifest?->id, $enabledIds, true),
                ],
                $inspections,
            ),
        ]);
    }
}
