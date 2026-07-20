<?php

namespace App\Platform\Extensions;

use InvalidArgumentException;

final class ExtensionRegistry
{
    /**
     * Discover manifests from direct children of configured local directories.
     *
     * @param  list<string>|null  $paths
     * @return array<string, ExtensionManifest>
     */
    public function discover(?array $paths = null): array
    {
        $configuredPaths = $paths ?? config('extensions.paths', []);

        if (! is_array($configuredPaths)) {
            throw new InvalidArgumentException('Extension paths configuration must be a list.');
        }

        $permissions = config('extensions.permissions', []);
        $uiSlots = config('extensions.ui_slots', []);

        if (! is_array($permissions) || ! is_array($uiSlots)) {
            throw new InvalidArgumentException('Extension allowlists must be lists.');
        }

        $extensions = [];

        foreach ($configuredPaths as $path) {
            if (! is_string($path)) {
                throw new InvalidArgumentException('Each extension path must be a string.');
            }

            $root = realpath($path);

            if ($root === false || ! is_dir($root)) {
                continue;
            }

            $manifests = glob($root.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'extension.json') ?: [];

            foreach ($manifests as $manifestPath) {
                $realManifestPath = realpath($manifestPath);
                $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

                if ($realManifestPath === false || ! str_starts_with($realManifestPath, $rootPrefix)) {
                    throw new InvalidArgumentException('Extension manifest resolves outside its configured root.');
                }

                /** @var list<string> $permissions */
                /** @var list<string> $uiSlots */
                $manifest = ExtensionManifest::fromFile($realManifestPath, $permissions, $uiSlots);

                if (isset($extensions[$manifest->id])) {
                    throw new InvalidArgumentException("Duplicate extension id: {$manifest->id}");
                }

                $extensions[$manifest->id] = $manifest;
            }
        }

        ksort($extensions);

        return $extensions;
    }
}
