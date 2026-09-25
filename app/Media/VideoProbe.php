<?php

namespace App\Media;

use JsonException;
use Symfony\Component\Process\Process;

final class VideoProbe
{
    public function probe(string $absolutePath): VideoProbeResult
    {
        $process = new Process([
            'ffprobe', '-v', 'error', '-protocol_whitelist', 'file,pipe',
            '-show_entries', 'format=duration,format_name:stream=codec_type,codec_name,width,height,avg_frame_rate',
            '-of', 'json', $absolutePath,
        ]);
        $process->setTimeout(20);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new VideoProcessingException('unsupported_media');
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new VideoProcessingException('unsupported_media');
        }

        if (! is_array($result) || ! is_array($result['format'] ?? null)) {
            throw new VideoProcessingException('unsupported_media');
        }

        $format = $result['format'];
        $formatName = $format['format_name'] ?? null;
        $allowedFormats = [
            'mov,mp4,m4a,3gp,3g2,mj2',
            'matroska,webm',
        ];

        if (! is_string($formatName) || ! in_array($formatName, $allowedFormats, true)) {
            throw new VideoProcessingException('unsupported_media');
        }

        $duration = filter_var($format['duration'] ?? null, FILTER_VALIDATE_FLOAT);
        $maxDuration = min(90, max(1, (int) config('media.video.max_duration_seconds', 90)));

        if (! is_float($duration) || ! is_finite($duration) || $duration <= 0 || $duration > $maxDuration) {
            throw new VideoProcessingException('invalid_duration');
        }

        $streams = $result['streams'] ?? null;

        if (! is_array($streams) || count($streams) < 1 || count($streams) > 2) {
            throw new VideoProcessingException('unsupported_media');
        }

        $videos = array_values(array_filter($streams, static fn (mixed $stream): bool => is_array($stream) && ($stream['codec_type'] ?? null) === 'video'));
        $audios = array_values(array_filter($streams, static fn (mixed $stream): bool => is_array($stream) && ($stream['codec_type'] ?? null) === 'audio'));

        if (count($videos) !== 1 || count($audios) > 1 || count($videos) + count($audios) !== count($streams)) {
            throw new VideoProcessingException('unsupported_media');
        }

        $video = $videos[0];
        $width = $video['width'] ?? null;
        $height = $video['height'] ?? null;
        $codec = $video['codec_name'] ?? null;
        $audioCodec = $audios[0]['codec_name'] ?? null;

        if (! is_int($width) || ! is_int($height)
            || $width < 2 || $height < 2
            || max($width, $height) > 1920
            || $width * $height > 1920 * 1080
            || ! is_string($codec)
            || ! in_array($codec, ['h264', 'hevc', 'vp8', 'vp9'], true)
            || ($audioCodec !== null && (! is_string($audioCodec)
                || ! in_array($audioCodec, ['aac', 'opus', 'vorbis', 'mp3', 'pcm_s16le'], true)))) {
            throw new VideoProcessingException('unsupported_media');
        }

        $frameRate = $video['avg_frame_rate'] ?? null;

        if (! is_string($frameRate) || ! preg_match('/^(\d+)\/(\d+)$/', $frameRate, $parts)
            || (int) $parts[2] < 1 || (int) $parts[1] / (int) $parts[2] > 60) {
            throw new VideoProcessingException('unsupported_media');
        }

        return new VideoProbeResult(
            (int) round($duration * 1000),
            $width,
            $height,
            $codec,
            is_string($audioCodec) ? $audioCodec : null,
        );
    }
}
