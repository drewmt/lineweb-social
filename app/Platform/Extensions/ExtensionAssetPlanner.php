<?php

namespace App\Platform\Extensions;

use Throwable;

final class ExtensionAssetPlanner
{
    /** @var array<string, ExtensionAssetPlan> */
    private array $plans = [];

    public function __construct(
        private readonly ExtensionAssetReceiptStore $receipts,
    ) {}

    public function plan(ExtensionInspection $inspection): ExtensionAssetPlan
    {
        $manifest = $inspection->manifest;

        if (! $manifest instanceof ExtensionManifest) {
            throw new ExtensionActivationException('Cannot plan assets for an invalid extension manifest.');
        }

        $cacheKey = implode('|', [
            $manifest->id,
            $manifest->version,
            $inspection->rootPath ?? '',
        ]);

        return $this->plans[$cacheKey] ??= $this->build($inspection, $manifest);
    }

    public function forget(string $extensionId): void
    {
        foreach (array_keys($this->plans) as $key) {
            if (str_starts_with($key, $extensionId.'|')) {
                unset($this->plans[$key]);
            }
        }
    }

    private function build(
        ExtensionInspection $inspection,
        ExtensionManifest $manifest,
    ): ExtensionAssetPlan {
        $declared = [
            ...array_map(
                static fn (string $path): array => [ExtensionAssetFile::TYPE_STYLE, $path],
                $manifest->styleAssets,
            ),
            ...array_map(
                static fn (string $path): array => [ExtensionAssetFile::TYPE_SCRIPT, $path],
                $manifest->scriptAssets,
            ),
        ];

        if ($declared === []) {
            return new ExtensionAssetPlan(
                manifest: $manifest,
                files: [],
                status: ExtensionAssetPlan::STATUS_NONE,
                message: 'This extension declares no browser assets.',
            );
        }

        if ($inspection->rootPath === null) {
            return $this->blocked($manifest, [], 'Extension source directory is unavailable.');
        }

        $root = realpath($inspection->rootPath);

        if ($root === false || ! is_dir($root)) {
            return $this->blocked($manifest, [], 'Extension source directory is unavailable.');
        }

        $maximumFiles = (int) config('extensions.assets.max_files', 12);
        $maximumFileBytes = (int) config('extensions.assets.max_file_bytes', 524288);
        $maximumTotalBytes = (int) config('extensions.assets.max_total_bytes', 2097152);

        if (count($declared) > $maximumFiles) {
            return $this->blocked(
                $manifest,
                [],
                "Asset declaration exceeds the {$maximumFiles}-file limit.",
            );
        }

        $assetFiles = [];
        $problems = [];
        $totalBytes = 0;
        $seenPaths = [];

        foreach ($declared as [$type, $declaredPath]) {
            if (isset($seenPaths[$declaredPath])) {
                $problems[] = "{$declaredPath}: the same file cannot be declared more than once.";

                continue;
            }

            $seenPaths[$declaredPath] = true;
            $candidate = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $declaredPath);
            $realPath = realpath($candidate);

            if ($this->containsSymlink($root, $declaredPath)
                || $realPath === false
                || ! is_file($realPath)
                || ! is_readable($realPath)
                || ! str_starts_with($realPath, $root.DIRECTORY_SEPARATOR)) {
                $problems[] = "{$declaredPath}: asset must be a readable regular file inside the extension.";

                continue;
            }

            $size = filesize($realPath);

            if (! is_int($size) || $size < 1 || $size > $maximumFileBytes) {
                $problems[] = "{$declaredPath}: asset must be between 1 and {$maximumFileBytes} bytes.";

                continue;
            }

            $sample = file_get_contents($realPath, false, null, 0, min($size, 8192));

            if (! is_string($sample) || str_contains($sample, "\0")) {
                $problems[] = "{$declaredPath}: browser assets must be text files.";

                continue;
            }

            $checksum = hash_file('sha256', $realPath);

            if (! is_string($checksum)) {
                $problems[] = "{$declaredPath}: asset checksum could not be calculated.";

                continue;
            }

            $totalBytes += $size;
            $assetFiles[] = new ExtensionAssetFile(
                type: $type,
                declaredPath: $declaredPath,
                sourcePath: $realPath,
                checksum: $checksum,
                bytes: $size,
            );
        }

        if ($totalBytes > $maximumTotalBytes) {
            $problems[] = "Declared browser assets exceed the {$maximumTotalBytes}-byte total limit.";
        }

        if ($problems !== []) {
            return new ExtensionAssetPlan(
                manifest: $manifest,
                files: $assetFiles,
                status: ExtensionAssetPlan::STATUS_BLOCKED,
                message: 'Asset source integrity requires operator attention.',
                problems: $problems,
            );
        }

        $release = $this->release($manifest, $assetFiles);

        try {
            $receipt = $this->receipts->read($manifest->id);
        } catch (ExtensionAssetException $exception) {
            return $this->blocked($manifest, $assetFiles, $exception->getMessage(), $release);
        }

        if ($receipt === null
            || $receipt['extensionVersion'] !== $manifest->version
            || $receipt['release'] !== $release) {
            return new ExtensionAssetPlan(
                manifest: $manifest,
                files: $assetFiles,
                status: ExtensionAssetPlan::STATUS_UNPUBLISHED,
                message: count($assetFiles).' reviewed browser asset'.(count($assetFiles) === 1 ? ' is' : 's are').' waiting for publication.',
                release: $release,
            );
        }

        $expected = array_map(
            fn (ExtensionAssetFile $file): array => $this->receiptAsset($manifest, $release, $file),
            $assetFiles,
        );

        if ($receipt['assets'] !== $expected) {
            return $this->blocked(
                $manifest,
                $assetFiles,
                'Published asset receipt does not match the reviewed source.',
                $release,
            );
        }

        $publicRoot = $this->publicRoot();
        $publicPrefix = $publicRoot.DIRECTORY_SEPARATOR;

        foreach ($expected as $asset) {
            $candidate = $publicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $asset['file']);
            $realPath = realpath($candidate);

            if ($realPath === false
                || ! is_file($realPath)
                || is_link($candidate)
                || ! str_starts_with($realPath, $publicPrefix)
                || filesize($realPath) !== $asset['bytes']) {
                $problems[] = "{$asset['path']}: published file is missing or has the wrong size.";

                continue;
            }

            try {
                $publishedChecksum = hash_file('sha256', $realPath);
            } catch (Throwable) {
                $publishedChecksum = false;
            }

            if (! is_string($publishedChecksum)
                || ! hash_equals($asset['checksum'], $publishedChecksum)) {
                $problems[] = "{$asset['path']}: published file failed its integrity check.";
            }
        }

        if ($problems !== []) {
            return new ExtensionAssetPlan(
                manifest: $manifest,
                files: $assetFiles,
                status: ExtensionAssetPlan::STATUS_BLOCKED,
                message: 'Published browser assets failed integrity checks.',
                release: $release,
                problems: $problems,
            );
        }

        return new ExtensionAssetPlan(
            manifest: $manifest,
            files: $assetFiles,
            status: ExtensionAssetPlan::STATUS_PUBLISHED,
            message: 'Every declared browser asset matches its immutable published release.',
            release: $release,
            publishedAssets: $expected,
        );
    }

    /**
     * @param  list<ExtensionAssetFile>  $files
     */
    private function release(ExtensionManifest $manifest, array $files): string
    {
        $parts = array_map(
            static fn (ExtensionAssetFile $file): array => [
                $file->type,
                $file->declaredPath,
                $file->checksum,
                $file->bytes,
            ],
            $files,
        );

        return substr(hash('sha256', json_encode([
            $manifest->id,
            $manifest->version,
            $parts,
        ], JSON_THROW_ON_ERROR)), 0, 20);
    }

    /**
     * @return array{type: string, path: string, checksum: string, bytes: int, file: string}
     */
    public function receiptAsset(
        ExtensionManifest $manifest,
        string $release,
        ExtensionAssetFile $file,
    ): array {
        return [
            'type' => $file->type,
            'path' => $file->declaredPath,
            'checksum' => $file->checksum,
            'bytes' => $file->bytes,
            'file' => implode('/', [
                $manifest->id,
                $manifest->version,
                $release,
                $file->publishedFilename(),
            ]),
        ];
    }

    private function publicRoot(): string
    {
        $root = config('extensions.assets.public_root');

        if (! is_string($root) || trim($root) === '') {
            throw new ExtensionAssetException('Extension public asset storage is not configured.');
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function containsSymlink(string $root, string $declaredPath): bool
    {
        $candidate = $root;

        foreach (explode('/', $declaredPath) as $segment) {
            $candidate .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<ExtensionAssetFile>  $files
     */
    private function blocked(
        ExtensionManifest $manifest,
        array $files,
        string $message,
        ?string $release = null,
    ): ExtensionAssetPlan {
        return new ExtensionAssetPlan(
            manifest: $manifest,
            files: $files,
            status: ExtensionAssetPlan::STATUS_BLOCKED,
            message: $message,
            release: $release,
            problems: [$message],
        );
    }
}
