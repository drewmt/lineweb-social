<?php

namespace App\Http\Controllers\Api\V1;

use App\Community\VisiblePostQuery;
use App\Http\Controllers\Controller;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PostMediaController extends Controller
{
    public function __invoke(
        Request $request,
        string $post,
        VisiblePostQuery $visiblePosts,
    ): Response {
        /** @var User $viewer */
        $viewer = $request->user();
        $visiblePost = $visiblePosts->findVisible($viewer, $post);
        $media = $visiblePost->media;

        abort_unless($media instanceof PostMedia, 404);

        $storage = Storage::disk($media->disk);

        abort_unless($storage->exists($media->path), 404);

        $contents = $storage->get($media->path);
        $response = response($contents, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="post-image.webp"',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-site',
            'Vary' => 'Authorization, Origin',
        ]);
        $response->setPrivate();
        $response->setMaxAge(3600);
        $response->setEtag($media->checksum);
        $response->isNotModified($request);

        return $response;
    }
}
