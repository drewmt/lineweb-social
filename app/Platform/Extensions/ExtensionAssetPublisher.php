<?php

namespace App\Platform\Extensions;

use Illuminate\Filesystem\Filesystem;
use Throwable;

final class ExtensionAssetPublisher
{
    public function __construct(
        private readonly ExtensionInspector $inspector,
        private readonly ExtensionAssetPlanner $planner,
        private readonly ExtensionActivator $activator,
        private readonly ExtensionAssetReceiptStore $receipts,
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  list<string>  $ids
     * @return array<string, ExtensionAssetPlan>
     */
    public function publish(array $ids): array
    {
        $plans = $this->preflight($ids);
        $published = [];

        foreach ($plans as $id => $plan) {
            if ($plan->status === ExtensionAssetPlan::STATUS_UNPUBLISHED) {
                $this->publishPlan($plan);
                $this->planner->forget($id);
                $plan = $this->planner->plan($this->inspection($id));

                if ($plan->status !== ExtensionAssetPlan::STATUS_PUBLISHED) {
                    throw new ExtensionAssetException("Extension '{$id}' assets did not pass post-publication verification.");
                }
            }

            $published[$id] = $plan;
        }

        return $published;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, ExtensionAssetPlan>
     */
    private function preflight(array $ids): array
    {
        if ($ids === [] || count($ids) !== count(array_unique($ids))) {
            throw new ExtensionAssetException('Select one or more unique extension ids.');
        }

        $inspections = $this->inspector->inspect();
        $byId = [];

        foreach ($inspections as $inspection) {
            if ($inspection->manifest instanceof ExtensionManifest) {
                $byId[$inspection->manifest->id] = $inspection;
            }
        }

        $plans = [];

        foreach ($ids as $id) {
            $inspection = $byId[$id] ?? null;

            if (! $inspection instanceof ExtensionInspection) {
                throw new ExtensionAssetException("Extension '{$id}' was not found.");
            }

            if (! $inspection->isCompatible()) {
                throw new ExtensionAssetException(
                    "Extension '{$id}' cannot publish assets: {$inspection->message}",
                );
            }

            if ($this->activator->isEnabled($id)) {
                throw new ExtensionAssetException(
                    "Disable extension '{$id}' before publishing a browser asset release.",
                );
            }

            $plan = $this->planner->plan($inspection);

            if ($plan->status === ExtensionAssetPlan::STATUS_BLOCKED) {
                throw new ExtensionAssetException(
                    "Extension '{$id}' cannot publish assets: {$plan->message} ".implode(' ', $plan->problems),
                );
            }

            $plans[$id] = $plan;
        }

        return $plans;
    }

    private function publishPlan(ExtensionAssetPlan $plan): void
    {
        $release = $plan->release;

        if ($release === null) {
            throw new ExtensionAssetException("Extension '{$plan->manifest->id}' has no publishable asset release.");
        }

        $assets = array_map(
            fn (ExtensionAssetFile $file): array => $this->planner->receiptAsset(
                $plan->manifest,
                $release,
                $file,
            ),
            $plan->files,
        );
        $publicRoot = $this->publicRoot();
        $releaseDirectory = $publicRoot.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, [
            $plan->manifest->id,
            $plan->manifest->version,
            $release,
        ]);

        if ($this->files->isDirectory($releaseDirectory)) {
            $this->verifyExistingRelease($releaseDirectory, $assets);
        } else {
            $this->writeRelease($publicRoot, $releaseDirectory, $plan->files, $assets);
        }

        $this->receipts->write($plan->manifest, $release, $assets);
    }

    /**
     * @param  list<ExtensionAssetFile>  $sourceFiles
     * @param  list<array{type: string, path: string, checksum: string, bytes: int, file: string}>  $assets
     */
    private function writeRelease(
        string $publicRoot,
        string $releaseDirectory,
        array $sourceFiles,
        array $assets,
    ): void {
        $this->files->ensureDirectoryExists($publicRoot, 0755, true);
        $temporary = $publicRoot.DIRECTORY_SEPARATOR.'.publish-'.bin2hex(random_bytes(10));
        $this->files->ensureDirectoryExists($temporary, 0755, true);

        try {
            foreach ($sourceFiles as $index => $source) {
                $destination = $temporary.DIRECTORY_SEPARATOR.basename($assets[$index]['file']);

                if (! $this->files->copy($source->sourcePath, $destination)) {
                    throw new ExtensionAssetException("Unable to copy extension asset {$source->declaredPath}.");
                }

                $checksum = hash_file('sha256', $destination);

                if (! is_string($checksum) || ! hash_equals($source->checksum, $checksum)) {
                    throw new ExtensionAssetException("Extension asset {$source->declaredPath} changed during publication.");
                }
            }

            $this->files->ensureDirectoryExists(dirname($releaseDirectory), 0755, true);

            if (! @rename($temporary, $releaseDirectory)) {
                throw new ExtensionAssetException('Unable to publish the immutable extension asset directory.');
            }
        } catch (Throwable $exception) {
            $this->files->deleteDirectory($temporary);

            if ($exception instanceof ExtensionAssetException) {
                throw $exception;
            }

            throw new ExtensionAssetException(
                'Extension asset publication failed.',
                previous: $exception,
            );
        }
    }

    /**
     * @param  list<array{type: string, path: string, checksum: string, bytes: int, file: string}>  $assets
     */
    private function verifyExistingRelease(string $directory, array $assets): void
    {
        $expected = array_map(
            static fn (array $asset): string => basename($asset['file']),
            $assets,
        );
        $actual = array_values(array_map(
            static fn (string $path): string => basename($path),
            $this->files->files($directory),
        ));
        sort($expected);
        sort($actual);

        if ($actual !== $expected) {
            throw new ExtensionAssetException('Existing immutable extension asset directory contains unexpected files.');
        }

        foreach ($assets as $asset) {
            $path = $directory.DIRECTORY_SEPARATOR.basename($asset['file']);
            $checksum = hash_file('sha256', $path);

            if (! is_string($checksum) || ! hash_equals($asset['checksum'], $checksum)) {
                throw new ExtensionAssetException("Existing published asset {$asset['path']} failed integrity verification.");
            }
        }
    }

    private function inspection(string $id): ExtensionInspection
    {
        foreach ($this->inspector->inspect() as $inspection) {
            if ($inspection->manifest?->id === $id) {
                return $inspection;
            }
        }

        throw new ExtensionAssetException("Extension '{$id}' disappeared during asset publication.");
    }

    private function publicRoot(): string
    {
        $root = config('extensions.assets.public_root');

        if (! is_string($root) || trim($root) === '') {
            throw new ExtensionAssetException('Extension public asset storage is not configured.');
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }
}
