<?php

namespace App\Console\Commands;

use App\Jobs\MediaWorkerHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class VideoPreflight extends Command
{
    protected $signature = 'media:video-preflight';

    protected $description = 'Read-only readiness checks for private video posts';

    public function handle(): int
    {
        $checks = [];
        $diskName = config('media.disk');
        $localDisk = is_string($diskName)
            && $diskName !== ''
            && config("filesystems.disks.{$diskName}.driver") === 'local';
        $checks['Private local media disk'] = $localDisk;
        $root = $localDisk ? Storage::disk($diskName)->path('') : null;
        $checks['Writable private media directory'] = is_string($root)
            && is_dir($root)
            && is_writable($root);
        $freeBytes = is_string($root) && is_dir($root) ? disk_free_space($root) : false;
        $minimumFree = max(
            1024 * 1024 * 1024,
            4 * (int) config('media.video.max_input_kilobytes', 65536) * 1024,
        );
        $checks['At least 1 GiB of free media disk capacity'] = is_float($freeBytes)
            && $freeBytes >= $minimumFree;

        $checks['FFmpeg with H.264 encoder'] = $this->binaryWorks('ffmpeg', ['-hide_banner', '-encoders'], 'libx264');
        $checks['FFprobe'] = $this->binaryWorks('ffprobe', ['-version']);
        $checks['GD WebP poster support'] = extension_loaded('gd') && function_exists('imagewebp');

        $maxBytes = min(64 * 1024 * 1024, (int) config('media.video.max_input_kilobytes', 65536) * 1024);
        $checks['PHP upload_max_filesize covers the input limit'] = $this->iniBytes('upload_max_filesize') >= $maxBytes;
        $checks['PHP post_max_size covers upload overhead'] = $this->iniBytes('post_max_size') >= $maxBytes + 1024 * 1024;

        $queue = config('queue.default');
        $cache = config('cache.default');
        $checks['Asynchronous queue connection'] = is_string($queue) && ! in_array($queue, ['', 'sync', 'null'], true);
        $checks['Shared cache for worker heartbeat'] = is_string($cache) && ! in_array($cache, ['', 'array', 'null'], true);
        $heartbeat = null;

        if ($checks['Shared cache for worker heartbeat']) {
            try {
                $heartbeat = Cache::get(MediaWorkerHeartbeat::cacheKey());
            } catch (Throwable) {
                $heartbeat = null;
            }
        }
        $checks['Dedicated media worker heartbeat within 3 minutes'] = is_int($heartbeat)
            && $heartbeat >= now()->subMinutes(3)->timestamp;

        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS' : 'FAIL').': '.$label);
        }

        $this->line('Video posts are '.(config('media.video.enabled') ? 'enabled' : 'disabled').' in this application configuration.');
        $this->line('Check the web PHP-FPM upload limits separately; CLI PHP settings may differ.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    /** @param list<string> $arguments */
    private function binaryWorks(string $binary, array $arguments, ?string $requiredOutput = null): bool
    {
        try {
            $process = new Process([$binary, ...$arguments]);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful()
                && ($requiredOutput === null || str_contains($process->getOutput(), $requiredOutput));
        } catch (Throwable) {
            return false;
        }
    }

    private function iniBytes(string $setting): int
    {
        $value = trim((string) ini_get($setting));

        if (! preg_match('/^(\d+)([KMG]?)$/iD', $value, $matches)) {
            return 0;
        }

        $multiplier = match (strtoupper($matches[2])) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        return (int) $matches[1] * $multiplier;
    }
}
