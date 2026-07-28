<?php

namespace App\Platform\Extensions;

final readonly class ExtensionAssetFile
{
    public const TYPE_SCRIPT = 'script';

    public const TYPE_STYLE = 'style';

    public function __construct(
        public string $type,
        public string $declaredPath,
        public string $sourcePath,
        public string $checksum,
        public int $bytes,
    ) {}

    public function integrity(): string
    {
        $binary = hex2bin($this->checksum);

        if ($binary === false) {
            throw new ExtensionAssetException("Invalid checksum for extension asset {$this->declaredPath}.");
        }

        return 'sha256-'.base64_encode($binary);
    }

    public function publishedFilename(): string
    {
        $pathHash = substr(hash('sha256', $this->declaredPath), 0, 10);
        $contentHash = substr($this->checksum, 0, 16);
        $extension = strtolower((string) pathinfo($this->declaredPath, PATHINFO_EXTENSION));

        return "{$this->type}-{$pathHash}-{$contentHash}.{$extension}";
    }
}
