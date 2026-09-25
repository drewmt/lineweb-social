<?php

namespace App\Media;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class VideoStorage
{
    /** @return array{temporaryOutput: string, temporaryPoster: string, output: string, poster: string} */
    public function paths(int $videoId): array
    {
        $token = (string) Str::uuid();
        $disk = $this->disk();

        foreach (["videos/tmp/{$videoId}", "videos/ready/{$videoId}"] as $directory) {
            if (! Storage::disk($disk)->makeDirectory($directory)) {
                throw new RuntimeException('The private video directory could not be created.');
            }
        }

        return [
            'temporaryOutput' => "videos/tmp/{$videoId}/{$token}.mp4",
            'temporaryPoster' => "videos/tmp/{$videoId}/{$token}.webp",
            'output' => "videos/ready/{$videoId}/{$token}.mp4",
            'poster' => "videos/ready/{$videoId}/{$token}.webp",
        ];
    }

    public function absolute(string $path): string
    {
        $this->guardPath($path);

        return Storage::disk($this->disk())->path($path);
    }

    public function size(string $path): int
    {
        $this->guardPath($path);
        $size = Storage::disk($this->disk())->size($path);

        if ($size < 1) {
            throw new RuntimeException('The processed video asset is empty.');
        }

        return $size;
    }

    public function checksum(string $path): string
    {
        $checksum = hash_file('sha256', $this->absolute($path));

        if (! is_string($checksum)) {
            throw new RuntimeException('The processed video checksum could not be read.');
        }

        return $checksum;
    }

    public function promote(string $temporary, string $ready): void
    {
        $this->guardPath($temporary);
        $this->guardPath($ready);

        if (! str_starts_with($temporary, 'videos/tmp/')
            || ! str_starts_with($ready, 'videos/ready/')
            || ! Storage::disk($this->disk())->move($temporary, $ready)) {
            throw new RuntimeException('The processed video asset could not be promoted.');
        }
    }

    public function delete(?string $path): void
    {
        if ($path === null) {
            return;
        }

        $this->guardPath($path);
        Storage::disk($this->disk())->delete($path);
    }

    private function disk(): string
    {
        $disk = config('media.disk');

        if (! is_string($disk) || $disk === '' || config("filesystems.disks.{$disk}.driver") !== 'local') {
            throw new RuntimeException('Video processing requires a private local media disk.');
        }

        return $disk;
    }

    private function guardPath(string $path): void
    {
        if (! preg_match('~^videos/source/[0-9a-f-]{36}\.upload$|^videos/(?:tmp|ready)/[0-9]+/[0-9a-f-]{36}\.(?:mp4|webp)$~D', $path)) {
            throw new RuntimeException('Invalid private video path.');
        }
    }
}
