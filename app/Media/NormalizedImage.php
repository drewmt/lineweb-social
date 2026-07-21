<?php

namespace App\Media;

final readonly class NormalizedImage
{
    public function __construct(
        public string $contents,
        public int $width,
        public int $height,
        public string $mimeType,
        public int $sizeBytes,
        public string $checksum,
    ) {}
}
