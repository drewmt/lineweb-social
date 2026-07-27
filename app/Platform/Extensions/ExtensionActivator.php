<?php

namespace App\Platform\Extensions;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class ExtensionActivator
{
    public function __construct(
        private readonly Application $app,
        private readonly ExtensionInspector $inspector,
    ) {}

    /**
     * Validate every configured extension before registering any provider.
     */
    public function activateConfigured(): void
    {
        $providers = $this->preflight();

        foreach ($providers as $id => $provider) {
            try {
                $this->app->register($provider);
            } catch (Throwable $exception) {
                throw new ExtensionActivationException(
                    "Extension '{$id}' failed while registering {$provider}.",
                    previous: $exception,
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    public function enabledIds(): array
    {
        $enabled = config('extensions.enabled', []);

        if (! is_array($enabled) || ! array_is_list($enabled)) {
            throw new ExtensionActivationException('Enabled extensions configuration must be a list.');
        }

        $normalized = [];

        foreach ($enabled as $id) {
            if (! is_string($id) || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $id) !== 1) {
                throw new ExtensionActivationException('Every enabled extension must use a valid kebab-case id.');
            }

            if (in_array($id, $normalized, true)) {
                throw new ExtensionActivationException("Enabled extension '{$id}' is configured more than once.");
            }

            $normalized[] = $id;
        }

        return $normalized;
    }

    public function isEnabled(?string $id): bool
    {
        return $id !== null && in_array($id, $this->enabledIds(), true);
    }

    /**
     * @return array<string, class-string<ServiceProvider>>
     */
    private function preflight(): array
    {
        $enabled = $this->enabledIds();

        if ($enabled === []) {
            return [];
        }

        $inspections = $this->inspector->inspect();
        $byId = [];

        foreach ($inspections as $inspection) {
            if ($inspection->manifest instanceof ExtensionManifest) {
                $byId[$inspection->manifest->id] = $inspection;
            }
        }

        $providers = [];
        $providerOwners = [];

        foreach ($enabled as $id) {
            $inspection = $byId[$id] ?? null;

            if (! $inspection instanceof ExtensionInspection) {
                throw new ExtensionActivationException("Enabled extension '{$id}' was not found.");
            }

            if (! $inspection->isCompatible()) {
                throw new ExtensionActivationException(
                    "Enabled extension '{$id}' cannot start: {$inspection->message}",
                );
            }

            $provider = $inspection->manifest?->provider;

            if (! is_string($provider)) {
                throw new ExtensionActivationException("Enabled extension '{$id}' has no valid provider.");
            }

            try {
                $providerExists = class_exists($provider);
            } catch (Throwable $exception) {
                throw new ExtensionActivationException(
                    "Enabled extension '{$id}' provider {$provider} could not be autoloaded.",
                    previous: $exception,
                );
            }

            if (! $providerExists) {
                throw new ExtensionActivationException(
                    "Enabled extension '{$id}' provider {$provider} is not autoloadable.",
                );
            }

            if (! is_subclass_of($provider, ServiceProvider::class)) {
                throw new ExtensionActivationException(
                    "Enabled extension '{$id}' provider {$provider} must extend ".ServiceProvider::class.'.',
                );
            }

            if (isset($providerOwners[$provider])) {
                throw new ExtensionActivationException(
                    "Enabled extensions '{$providerOwners[$provider]}' and '{$id}' declare the same provider {$provider}.",
                );
            }

            $providerOwners[$provider] = $id;
            $providers[$id] = $provider;
        }

        return $providers;
    }
}
