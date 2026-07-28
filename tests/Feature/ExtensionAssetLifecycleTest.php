<?php

namespace Tests\Feature;

use App\Platform\Extensions\ExtensionActivationException;
use App\Platform\Extensions\ExtensionActivator;
use App\Platform\Extensions\ExtensionAssetPlan;
use App\Platform\Extensions\ExtensionAssetPlanner;
use App\Platform\Extensions\ExtensionInspector;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExtensionAssetLifecycleTest extends TestCase
{
    private string $temporaryRoot;

    private string $extensionRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = storage_path('framework/testing/extension-assets-'.Str::uuid());
        $this->extensionRoot = $this->temporaryRoot.'/source';

        $files = new Filesystem;
        $files->ensureDirectoryExists($this->extensionRoot);
        $files->copyDirectory(
            base_path('tests/Fixtures/extension-assets'),
            $this->extensionRoot,
        );

        config()->set('extensions.core_version', '0.1.0-alpha.1');
        config()->set('extensions.paths', [$this->extensionRoot]);
        config()->set('extensions.enabled', []);
        config()->set('extensions.assets.public_root', $this->temporaryRoot.'/public');
        config()->set('extensions.assets.registry_root', $this->temporaryRoot.'/registry');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_planner_reports_bounded_reviewed_assets_without_publishing_them(): void
    {
        $plan = $this->plan();

        $this->assertSame(ExtensionAssetPlan::STATUS_UNPUBLISHED, $plan->status);
        $this->assertCount(2, $plan->files);
        $this->assertNotNull($plan->release);
        $this->assertDirectoryDoesNotExist($this->temporaryRoot.'/public');
        $this->assertFileDoesNotExist($this->temporaryRoot.'/registry/social-polls.json');
    }

    public function test_publish_command_creates_an_immutable_integrity_checked_release_once(): void
    {
        $this->assertSame(0, $this->publish());

        $plan = $this->freshPlan();
        $this->assertSame(ExtensionAssetPlan::STATUS_PUBLISHED, $plan->status);
        $this->assertCount(2, $plan->publishedAssets);
        $this->assertFileExists($this->temporaryRoot.'/registry/social-polls.json');

        foreach ($plan->publishedAssets as $asset) {
            $this->assertFileExists($this->temporaryRoot.'/public/'.$asset['file']);
            $this->assertSame(
                $asset['checksum'],
                hash_file('sha256', $this->temporaryRoot.'/public/'.$asset['file']),
            );
        }

        $before = file_get_contents($this->temporaryRoot.'/registry/social-polls.json');

        $this->assertSame(0, $this->publish());
        $this->assertSame(
            $before,
            file_get_contents($this->temporaryRoot.'/registry/social-polls.json'),
        );
    }

    public function test_all_selected_extensions_are_preflighted_before_the_first_public_write(): void
    {
        $this->assertSame(1, Artisan::call('platform:extensions:publish-assets', [
            'extension' => ['social-polls', 'missing-extension'],
        ]));

        $this->assertStringContainsString('was not found', Artisan::output());
        $this->assertDirectoryDoesNotExist($this->temporaryRoot.'/public');
        $this->assertFileDoesNotExist($this->temporaryRoot.'/registry/social-polls.json');
    }

    public function test_enabled_extension_cannot_publish_new_browser_assets(): void
    {
        config()->set('extensions.enabled', ['social-polls']);

        $this->assertSame(1, $this->publish());
        $this->assertStringContainsString('Disable extension', Artisan::output());
        $this->assertDirectoryDoesNotExist($this->temporaryRoot.'/public');
    }

    public function test_changed_source_requires_a_new_release_and_blocks_activation_until_published(): void
    {
        $this->assertSame(0, $this->publish());

        file_put_contents(
            $this->extensionRoot.'/social-polls/dist/social-polls.js',
            "\nwindow.changedAfterReview = true;\n",
            FILE_APPEND,
        );

        $plan = $this->freshPlan();
        $this->assertSame(ExtensionAssetPlan::STATUS_UNPUBLISHED, $plan->status);

        config()->set('extensions.enabled', ['social-polls']);

        $this->expectException(ExtensionActivationException::class);
        $this->expectExceptionMessage('cannot start');

        $this->app->make(ExtensionActivator::class)->activateConfigured();
    }

    public function test_tampered_public_asset_fails_closed(): void
    {
        $this->assertSame(0, $this->publish());

        $plan = $this->freshPlan();
        $asset = $plan->publishedAssets[0];
        file_put_contents(
            $this->temporaryRoot.'/public/'.$asset['file'],
            str_repeat('x', $asset['bytes']),
        );

        $plan = $this->freshPlan();

        $this->assertSame(ExtensionAssetPlan::STATUS_BLOCKED, $plan->status);
        $this->assertStringContainsString('integrity', $plan->message);
    }

    public function test_published_enabled_assets_are_rendered_with_sri_and_change_inertia_version(): void
    {
        $this->assertSame(0, $this->publish());
        config()->set('extensions.enabled', ['social-polls']);
        $this->app->make(ExtensionActivator::class)->activateConfigured();

        $plan = $this->freshPlan();
        $response = $this->get('/');

        $response->assertOk();

        foreach ($plan->publishedAssets as $asset) {
            $response->assertSee('extensions/'.$asset['file'], false);
            $response->assertSee($this->integrity($asset['checksum']), false);
        }

        $this->assertNotSame(
            hash('sha256', ''),
            $response->headers->get('X-Inertia-Version'),
        );
    }

    private function publish(): int
    {
        return Artisan::call('platform:extensions:publish-assets', [
            'extension' => ['social-polls'],
        ]);
    }

    private function plan(): ExtensionAssetPlan
    {
        return $this->app->make(ExtensionAssetPlanner::class)
            ->plan((new ExtensionInspector)->inspect()[0]);
    }

    private function freshPlan(): ExtensionAssetPlan
    {
        $planner = $this->app->make(ExtensionAssetPlanner::class);
        $planner->forget('social-polls');

        return $this->plan();
    }

    private function integrity(string $checksum): string
    {
        return 'sha256-'.base64_encode((string) hex2bin($checksum));
    }
}
