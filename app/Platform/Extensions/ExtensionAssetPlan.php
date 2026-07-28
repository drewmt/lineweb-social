<?php

namespace App\Platform\Extensions;

final readonly class ExtensionAssetPlan
{
    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_NONE = 'none';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_UNPUBLISHED = 'unpublished';

    /**
     * @param  list<ExtensionAssetFile>  $files
     * @param  list<array{type: string, path: string, checksum: string, bytes: int, file: string}>  $publishedAssets
     * @param  list<string>  $problems
     */
    public function __construct(
        public ExtensionManifest $manifest,
        public array $files,
        public string $status,
        public string $message,
        public ?string $release = null,
        public array $publishedAssets = [],
        public array $problems = [],
    ) {}

    public function isReadyForActivation(): bool
    {
        return in_array($this->status, [self::STATUS_NONE, self::STATUS_PUBLISHED], true);
    }

    /**
     * @return array{
     *     status: string,
     *     message: string,
     *     declared: int,
     *     published: int,
     *     blocked: int,
     *     release: string|null,
     *     items: list<array{type: string, path: string, bytes: int, status: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'declared' => count($this->files),
            'published' => count($this->publishedAssets),
            'blocked' => count($this->problems),
            'release' => $this->release,
            'items' => array_map(fn (ExtensionAssetFile $file): array => [
                'type' => $file->type,
                'path' => $file->declaredPath,
                'bytes' => $file->bytes,
                'status' => $this->status === self::STATUS_PUBLISHED
                    ? self::STATUS_PUBLISHED
                    : ($this->status === self::STATUS_BLOCKED
                        ? self::STATUS_BLOCKED
                        : self::STATUS_UNPUBLISHED),
            ], $this->files),
        ];
    }
}
