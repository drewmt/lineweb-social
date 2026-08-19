<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class StoryImageController extends Controller
{
    public function __invoke(Request $request, Story $story): Response
    {
        abort_if($story->expires_at->isPast(), 404);
        Gate::authorize('view', $story);
        abort_unless($story->hasImage(), 404);

        $storage = Storage::disk((string) $story->disk);
        abort_unless($storage->exists((string) $story->path), 404);

        $contents = $storage->get((string) $story->path);
        $response = response($contents, 200, [
            'Content-Type' => $story->mime_type ?? 'image/webp',
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="story-image.webp"',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Vary' => 'Cookie',
        ]);
        $response->setPrivate();
        $response->setMaxAge(900);

        if (filled($story->checksum)) {
            $response->setEtag((string) $story->checksum);
            $response->isNotModified($request);
        }

        return $response;
    }
}
