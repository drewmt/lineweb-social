<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PostImageController extends Controller
{
    public function primary(Request $request, Post $post): Response
    {
        Gate::authorize('view', $post);

        $media = $post->media()->firstOrFail();

        return $this->respond($request, $media);
    }

    public function show(Request $request, Post $post, string $media): Response
    {
        Gate::authorize('view', $post);

        $mediaItem = $post->mediaItems()->whereKey($media)->firstOrFail();

        return $this->respond($request, $mediaItem);
    }

    private function respond(Request $request, PostMedia $media): Response
    {
        $storage = Storage::disk($media->disk);

        abort_unless($storage->exists($media->path), 404);

        $contents = $storage->get($media->path);
        $response = response($contents, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="post-image.webp"',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Vary' => 'Cookie',
        ]);
        $response->setPrivate();
        $response->setMaxAge(3600);
        $response->setEtag($media->checksum);
        $response->isNotModified($request);

        return $response;
    }
}
