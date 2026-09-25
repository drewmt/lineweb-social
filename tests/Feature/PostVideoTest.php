<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostVideo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_is_disabled_by_default(): void
    {
        $this->assertFalse(config('media.video.enabled'));
    }

    public function test_video_is_unique_per_post(): void
    {
        $post = Post::factory()->create();

        PostVideo::query()->create([
            'post_id' => $post->id,
            'space_id' => $post->space_id,
            'status' => 'pending',
            'description' => 'Sample video',
        ]);

        $this->expectException(QueryException::class);

        PostVideo::query()->create([
            'post_id' => $post->id,
            'space_id' => $post->space_id,
            'status' => 'pending',
            'description' => 'A second video',
        ]);
    }
}
