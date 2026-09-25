<?php

namespace App\Http\Controllers;

use App\Media\VideoStorage;
use App\Models\Post;
use App\Models\PostVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostVideoController extends Controller
{
    public function video(Request $request, Post $post, VideoStorage $storage): Response
    {
        $video = $this->authorizedVideo($post);

        return $this->stream($request, $storage, $video, $video->output_path, 'video/mp4', true);
    }

    public function poster(Request $request, Post $post, VideoStorage $storage): Response
    {
        $video = $this->authorizedVideo($post);

        return $this->stream($request, $storage, $video, $video->poster_path, 'image/webp', false);
    }

    private function authorizedVideo(Post $post): PostVideo
    {
        Gate::authorize('view', $post);
        abort_if($post->hidden_at !== null, 403);

        $video = $post->video()->first();
        abort_unless($video instanceof PostVideo && $video->status === PostVideo::STATUS_READY, 404);

        return $video;
    }

    private function stream(
        Request $request,
        VideoStorage $storage,
        PostVideo $video,
        ?string $path,
        string $mime,
        bool $allowRange,
    ): Response {
        $extension = $allowRange ? '.mp4' : '.webp';
        abort_unless(
            is_string($path)
                && str_starts_with($path, "videos/ready/{$video->getKey()}/")
                && str_ends_with($path, $extension),
            404,
        );

        try {
            $absolute = $storage->absolute($path);
        } catch (\RuntimeException) {
            abort(404);
        }

        // Refuse symlinks and non-files even if a stored path has been tampered with.
        abort_unless(is_file($absolute) && ! is_link($absolute), 404);
        $size = filesize($absolute);
        abort_unless(is_int($size) && $size > 0, 404);

        $headers = [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Vary' => 'Cookie, Authorization',
            'Content-Disposition' => 'inline',
        ];

        if ($allowRange) {
            $headers['Accept-Ranges'] = 'bytes';
        }

        $range = $allowRange ? $request->header('Range') : null;
        $bounds = $range === null ? [0, $size - 1] : $this->parseRange($range, $size);

        if ($bounds === null) {
            return new Response('', 416, $headers + [
                'Content-Range' => "bytes */{$size}",
                'Content-Length' => '0',
            ]);
        }

        [$start, $end] = $bounds;
        $partial = $range !== null;
        $headers['Content-Length'] = (string) ($end - $start + 1);

        if ($partial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return new StreamedResponse(
            function () use ($request, $absolute, $start, $end): void {
                if ($request->isMethod('HEAD')) {
                    return;
                }

                $handle = fopen($absolute, 'rb');

                if ($handle === false) {
                    return;
                }

                try {
                    fseek($handle, $start);
                    $remaining = $end - $start + 1;

                    while ($remaining > 0 && ! feof($handle)) {
                        $chunk = fread($handle, min(65536, $remaining));

                        if ($chunk === false || $chunk === '') {
                            break;
                        }

                        echo $chunk;
                        $remaining -= strlen($chunk);
                    }
                } finally {
                    fclose($handle);
                }
            },
            $partial ? 206 : 200,
            $headers,
        );
    }

    /** @return array{int, int}|null */
    private function parseRange(string $range, int $size): ?array
    {
        if (! preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $matches)
            || ($matches[1] === '' && $matches[2] === '')) {
            return null;
        }

        if ($matches[1] === '') {
            $suffix = filter_var($matches[2], FILTER_VALIDATE_INT);

            return is_int($suffix) && $suffix > 0
                ? [max(0, $size - $suffix), $size - 1]
                : null;
        }

        $start = filter_var($matches[1], FILTER_VALIDATE_INT);
        $end = $matches[2] === '' ? $size - 1 : filter_var($matches[2], FILTER_VALIDATE_INT);

        if (! is_int($start) || ! is_int($end) || $start >= $size || $start > $end) {
            return null;
        }

        return [$start, min($end, $size - 1)];
    }
}
