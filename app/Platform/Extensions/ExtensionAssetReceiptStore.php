<?php

namespace App\Platform\Extensions;

use Illuminate\Filesystem\Filesystem;
use JsonException;

final class ExtensionAssetReceiptStore
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{
     *     extensionId: string,
     *     extensionVersion: string,
     *     release: string,
     *     publishedAt: string,
     *     assets: list<array{type: string, path: string, checksum: string, bytes: int, file: string}>
     * }|null
     */
    public function read(string $extensionId): ?array
    {
        $path = $this->path($extensionId);

        if (! $this->files->isFile($path)) {
            return null;
        }

        try {
            $payload = json_decode($this->files->get($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ExtensionAssetException(
                "Published asset receipt for '{$extensionId}' is not valid JSON.",
                previous: $exception,
            );
        }

        if (! is_array($payload)
            || ($payload['schema'] ?? null) !== 1
            || ($payload['extension_id'] ?? null) !== $extensionId
            || ! is_string($payload['extension_version'] ?? null)
            || ! is_string($payload['release'] ?? null)
            || preg_match('/^[a-f0-9]{20}$/', $payload['release']) !== 1
            || ! is_string($payload['published_at'] ?? null)
            || ! is_array($payload['assets'] ?? null)
            || ! array_is_list($payload['assets'])) {
            throw new ExtensionAssetException("Published asset receipt for '{$extensionId}' is invalid.");
        }

        $assets = [];

        foreach ($payload['assets'] as $asset) {
            if (! is_array($asset)
                || ! in_array($asset['type'] ?? null, [ExtensionAssetFile::TYPE_STYLE, ExtensionAssetFile::TYPE_SCRIPT], true)
                || ! is_string($asset['path'] ?? null)
                || ! is_string($asset['checksum'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $asset['checksum']) !== 1
                || ! is_int($asset['bytes'] ?? null)
                || $asset['bytes'] < 0
                || ! is_string($asset['file'] ?? null)
                || ! $this->isSafeRelativePath($asset['file'])) {
                throw new ExtensionAssetException("Published asset receipt for '{$extensionId}' contains an invalid file.");
            }

            $assets[] = [
                'type' => $asset['type'],
                'path' => $asset['path'],
                'checksum' => $asset['checksum'],
                'bytes' => $asset['bytes'],
                'file' => $asset['file'],
            ];
        }

        return [
            'extensionId' => $extensionId,
            'extensionVersion' => $payload['extension_version'],
            'release' => $payload['release'],
            'publishedAt' => $payload['published_at'],
            'assets' => $assets,
        ];
    }

    /**
     * @param  list<array{type: string, path: string, checksum: string, bytes: int, file: string}>  $assets
     */
    public function write(
        ExtensionManifest $manifest,
        string $release,
        array $assets,
    ): void {
        $directory = $this->root();
        $this->files->ensureDirectoryExists($directory, 0755, true);

        $contents = json_encode([
            'schema' => 1,
            'extension_id' => $manifest->id,
            'extension_version' => $manifest->version,
            'release' => $release,
            'published_at' => now()->toIso8601String(),
            'assets' => $assets,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $temporary = $directory.DIRECTORY_SEPARATOR.'.'.$manifest->id.'.'.bin2hex(random_bytes(8)).'.tmp';

        if ($this->files->put($temporary, $contents, true) === false
            || ! @rename($temporary, $this->path($manifest->id))) {
            $this->files->delete($temporary);

            throw new ExtensionAssetException("Unable to write the published asset receipt for '{$manifest->id}'.");
        }
    }

    private function path(string $extensionId): string
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $extensionId) !== 1) {
            throw new ExtensionAssetException('Asset receipt extension id is invalid.');
        }

        return $this->root().DIRECTORY_SEPARATOR.$extensionId.'.json';
    }

    private function root(): string
    {
        $root = config('extensions.assets.registry_root');

        if (! is_string($root) || trim($root) === '') {
            throw new ExtensionAssetException('Extension asset receipt storage is not configured.');
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function isSafeRelativePath(string $path): bool
    {
        $segments = explode('/', $path);

        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0")
            && preg_match('/^[A-Za-z0-9._\/+-]+$/', $path) === 1
            && ! in_array('', $segments, true)
            && ! in_array('.', $segments, true)
            && ! in_array('..', $segments, true);
    }
}
