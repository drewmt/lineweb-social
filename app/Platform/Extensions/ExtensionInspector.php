<?php

namespace App\Platform\Extensions;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

final class ExtensionInspector
{
    /**
     * Inspect every local manifest without loading its service provider.
     *
     * @param  array<array-key, mixed>|null  $paths
     * @return list<ExtensionInspection>
     */
    public function inspect(?array $paths = null): array
    {
        $configuredPaths = $paths ?? config('extensions.paths', []);
        $coreVersion = config('extensions.core_version');
        $permissions = config('extensions.permissions', []);
        $uiSlots = config('extensions.ui_slots', []);

        if (! is_array($configuredPaths) || ! array_is_list($configuredPaths)) {
            throw new InvalidArgumentException('Extension paths configuration must be a list.');
        }

        if (! is_string($coreVersion) || trim($coreVersion) === '') {
            throw new InvalidArgumentException('Extension core version must be a non-empty semantic version.');
        }

        try {
            (new VersionParser)->normalize($coreVersion);
        } catch (UnexpectedValueException $exception) {
            throw new InvalidArgumentException(
                'Extension core version must be a valid semantic version.',
                previous: $exception,
            );
        }

        if (! is_array($permissions) || ! array_is_list($permissions)
            || ! is_array($uiSlots) || ! array_is_list($uiSlots)) {
            throw new InvalidArgumentException('Extension allowlists must be lists.');
        }

        $inspections = [];

        foreach ($this->manifestPaths($configuredPaths) as $manifestPath) {
            $directory = basename(dirname($manifestPath));

            try {
                /** @var list<string> $permissions */
                /** @var list<string> $uiSlots */
                $manifest = ExtensionManifest::fromFile($manifestPath, $permissions, $uiSlots);

                $compatible = Semver::satisfies($coreVersion, $manifest->core);
                $inspections[] = new ExtensionInspection(
                    directory: $directory,
                    manifest: $manifest,
                    status: $compatible
                        ? ExtensionInspection::STATUS_COMPATIBLE
                        : ExtensionInspection::STATUS_INCOMPATIBLE,
                    message: $compatible
                        ? "Supports Lineweb Social {$coreVersion}."
                        : "Requires Lineweb Social {$manifest->core}; this installation is {$coreVersion}.",
                );
            } catch (Throwable $exception) {
                $inspections[] = new ExtensionInspection(
                    directory: $directory,
                    manifest: null,
                    status: ExtensionInspection::STATUS_INVALID,
                    message: $exception->getMessage(),
                );
            }
        }

        return $this->markDuplicateIds($inspections);
    }

    /**
     * @param  array<array-key, mixed>  $configuredPaths
     * @return list<string>
     */
    private function manifestPaths(array $configuredPaths): array
    {
        $manifestPaths = [];

        foreach ($configuredPaths as $path) {
            if (! is_string($path)) {
                throw new InvalidArgumentException('Each extension path must be a string.');
            }

            $root = realpath($path);

            if ($root === false || ! is_dir($root)) {
                continue;
            }

            $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $matches = glob($rootPrefix.'*'.DIRECTORY_SEPARATOR.'extension.json') ?: [];

            foreach ($matches as $manifestPath) {
                $realManifestPath = realpath($manifestPath);

                if ($realManifestPath === false || ! str_starts_with($realManifestPath, $rootPrefix)) {
                    throw new InvalidArgumentException('Extension manifest resolves outside its configured root.');
                }

                $manifestPaths[] = $realManifestPath;
            }
        }

        sort($manifestPaths);

        return array_values(array_unique($manifestPaths));
    }

    /**
     * @param  list<ExtensionInspection>  $inspections
     * @return list<ExtensionInspection>
     */
    private function markDuplicateIds(array $inspections): array
    {
        $idCounts = [];

        foreach ($inspections as $inspection) {
            if ($inspection->manifest instanceof ExtensionManifest) {
                $idCounts[$inspection->manifest->id] = ($idCounts[$inspection->manifest->id] ?? 0) + 1;
            }
        }

        $normalized = array_map(
            static function (ExtensionInspection $inspection) use ($idCounts): ExtensionInspection {
                $id = $inspection->manifest?->id;

                if ($id === null || ($idCounts[$id] ?? 0) < 2) {
                    return $inspection;
                }

                return new ExtensionInspection(
                    directory: $inspection->directory,
                    manifest: $inspection->manifest,
                    status: ExtensionInspection::STATUS_DUPLICATE,
                    message: "Extension id '{$id}' is declared by more than one local manifest.",
                );
            },
            $inspections,
        );

        usort(
            $normalized,
            static function (ExtensionInspection $left, ExtensionInspection $right): int {
                $leftName = $left->manifest instanceof ExtensionManifest
                    ? $left->manifest->name
                    : $left->directory;
                $rightName = $right->manifest instanceof ExtensionManifest
                    ? $right->manifest->name
                    : $right->directory;

                return [$leftName, $left->directory] <=> [$rightName, $right->directory];
            },
        );

        return $normalized;
    }
}
