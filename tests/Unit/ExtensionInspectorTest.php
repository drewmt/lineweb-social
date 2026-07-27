<?php

namespace Tests\Unit;

use App\Platform\Extensions\ExtensionInspection;
use App\Platform\Extensions\ExtensionInspector;
use App\Platform\Extensions\ExtensionManifest;
use Tests\TestCase;

class ExtensionInspectorTest extends TestCase
{
    public function test_inspector_isolates_compatible_incompatible_and_invalid_manifests(): void
    {
        config()->set('extensions.core_version', '0.1.0-alpha.1');

        $inspections = (new ExtensionInspector)->inspect([
            base_path('tests/Fixtures/extension-center'),
        ]);

        $this->assertCount(3, $inspections);
        $this->assertSame(
            [
                'Compatible Extension' => ExtensionInspection::STATUS_COMPATIBLE,
                'Next Core Extension' => ExtensionInspection::STATUS_INCOMPATIBLE,
                'incomplete' => ExtensionInspection::STATUS_INVALID,
            ],
            collect($inspections)
                ->mapWithKeys(fn (ExtensionInspection $inspection): array => [
                    ($inspection->manifest instanceof ExtensionManifest
                        ? $inspection->manifest->name
                        : $inspection->directory) => $inspection->status,
                ])
                ->all(),
        );
        $incompatible = collect($inspections)
            ->firstWhere('status', ExtensionInspection::STATUS_INCOMPATIBLE);

        $this->assertInstanceOf(ExtensionInspection::class, $incompatible);
        $this->assertStringContainsString(
            'this installation is 0.1.0-alpha.1',
            $incompatible->message,
        );
    }

    public function test_inspector_marks_every_manifest_that_declares_a_duplicate_id(): void
    {
        config()->set('extensions.core_version', '0.1.0-alpha.1');

        $inspections = (new ExtensionInspector)->inspect([
            base_path('tests/Fixtures/extensions'),
        ]);

        $this->assertCount(2, $inspections);
        $this->assertTrue(
            collect($inspections)->every(
                fn (ExtensionInspection $inspection): bool => $inspection->status === ExtensionInspection::STATUS_DUPLICATE,
            ),
        );
    }

    public function test_missing_extension_directories_are_treated_as_empty(): void
    {
        $this->assertSame(
            [],
            (new ExtensionInspector)->inspect([base_path('tests/Fixtures/missing-extensions')]),
        );
    }
}
