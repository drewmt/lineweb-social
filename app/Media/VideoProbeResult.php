<?php

namespace App\Media;

final readonly class VideoProbeResult
{
    public function __construct(
        public int $durationMs,
        public int $width,
        public int $height,
        public string $videoCodec,
        public ?string $audioCodec,
    ) {}
}
