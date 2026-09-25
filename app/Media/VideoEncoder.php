<?php

namespace App\Media;

use RuntimeException;
use Symfony\Component\Process\Process;

class VideoEncoder
{
    public function encode(
        string $source,
        string $output,
        string $poster,
        VideoProbeResult $meta,
    ): void {
        $maxHeight = min(720, max(2, (int) config('media.video.max_output_height', 720)));
        $scale = min(1, 1280 / $meta->width, $maxHeight / $meta->height);
        $width = max(2, (int) floor($meta->width * $scale / 2) * 2);
        $height = max(2, (int) floor($meta->height * $scale / 2) * 2);
        $timeout = min(300, max(30, (int) config('media.video.process_timeout_seconds', 180)));

        $encode = new Process([
            'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-protocol_whitelist', 'file,pipe', '-i', $source,
            '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-map_chapters', '-1',
            '-vf', "scale={$width}:{$height}",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28',
            '-pix_fmt', 'yuv420p', '-threads', '2',
            '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart',
            '-f', 'mp4', $output,
        ]);
        $encode->setTimeout($timeout);
        $encode->run();

        if (! $encode->isSuccessful()) {
            throw new RuntimeException('Video encoding failed.');
        }

        $posterProcess = new Process([
            'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-protocol_whitelist', 'file,pipe', '-ss', '0.5', '-i', $output,
            '-map', '0:v:0', '-frames:v', '1', '-an', '-map_metadata', '-1',
            '-c:v', 'png', '-f', 'image2pipe', 'pipe:1',
        ]);
        $posterProcess->setTimeout(30);
        $posterProcess->run();

        if (! $posterProcess->isSuccessful()) {
            throw new RuntimeException('Video poster generation failed.');
        }

        $image = imagecreatefromstring($posterProcess->getOutput());

        if ($image === false) {
            throw new RuntimeException('The video poster frame could not be read.');
        }

        try {
            if (! imagewebp($image, $poster, 75)) {
                throw new RuntimeException('The video poster could not be stored.');
            }
        } finally {
            imagedestroy($image);
        }
    }
}
