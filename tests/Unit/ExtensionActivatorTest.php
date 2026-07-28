<?php

namespace Tests\Unit;

use App\Platform\Extensions\ExtensionActivationException;
use App\Platform\Extensions\ExtensionActivator;
use App\Platform\Extensions\ExtensionInspector;
use App\Platform\Extensions\ExtensionMigrationPlanner;
use Tests\TestCase;

class ExtensionActivatorTest extends TestCase
{
    public function test_explicitly_enabled_extension_registers_its_provider(): void
    {
        $this->configure(['ready-extension']);

        $this->activator()->activateConfigured();

        $this->assertTrue($this->app->bound('extensions.ready'));
        $this->assertTrue($this->app->make('extensions.ready'));
    }

    public function test_provider_preflight_finishes_before_any_extension_registers(): void
    {
        $this->configure(['ready-extension', 'missing-extension']);

        try {
            $this->activator()->activateConfigured();
            $this->fail('A missing provider should stop extension activation.');
        } catch (ExtensionActivationException $exception) {
            $this->assertStringContainsString('is not autoloadable', $exception->getMessage());
        }

        $this->assertFalse($this->app->bound('extensions.ready'));
    }

    public function test_provider_must_extend_laravel_service_provider(): void
    {
        $this->configure(['not-provider']);

        $this->expectException(ExtensionActivationException::class);
        $this->expectExceptionMessage('must extend');

        $this->activator()->activateConfigured();
    }

    public function test_registration_failure_is_wrapped_with_extension_context(): void
    {
        $this->configure(['throwing-extension']);

        $this->expectException(ExtensionActivationException::class);
        $this->expectExceptionMessage("Extension 'throwing-extension' failed while registering");

        $this->activator()->activateConfigured();
    }

    public function test_unknown_or_duplicate_enabled_ids_are_rejected(): void
    {
        $this->configure(['unknown-extension']);

        try {
            $this->activator()->activateConfigured();
            $this->fail('An unknown extension should not activate.');
        } catch (ExtensionActivationException $exception) {
            $this->assertStringContainsString('was not found', $exception->getMessage());
        }

        $this->configure(['ready-extension', 'ready-extension']);

        $this->expectException(ExtensionActivationException::class);
        $this->expectExceptionMessage('configured more than once');

        $this->activator()->activateConfigured();
    }

    /** @param list<string> $enabled */
    private function configure(array $enabled): void
    {
        config()->set('extensions.core_version', '0.1.0-alpha.1');
        config()->set('extensions.paths', [
            base_path('tests/Fixtures/extension-activation'),
        ]);
        config()->set('extensions.enabled', $enabled);
    }

    private function activator(): ExtensionActivator
    {
        return new ExtensionActivator(
            $this->app,
            new ExtensionInspector,
            new ExtensionMigrationPlanner,
        );
    }
}
