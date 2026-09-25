<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        config(['media.disk' => 'media']);
    }

    public function test_video_upload_is_disabled_by_default(): void
    {
        [$author, $draft] = $this->draft();

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'A short demonstration',
        ])->assertNotFound();

        $this->assertDatabaseCount('post_videos', 0);
    }

    public function test_author_can_reserve_one_private_draft_video_but_cannot_publish_until_ready(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'A short demonstration',
        ])->assertSessionHasNoErrors();

        $video = $draft->fresh()->video;
        $this->assertInstanceOf(PostVideo::class, $video);
        $this->assertSame(PostVideo::STATUS_PENDING, $video->status);
        $this->assertSame(1024 * 1024, $video->reserved_bytes);
        Storage::disk('media')->assertExists($video->source_path);

        $this->actingAs($author)->post(route('drafts.publish', $draft), [
            'space' => $draft->space->slug,
            'body' => 'Demonstration',
        ])->assertSessionHasErrors('video');
        $this->assertNull($draft->fresh()->published_at);
    }

    public function test_non_author_cannot_upload_to_a_draft(): void
    {
        config(['media.video.enabled' => true]);
        [, $draft] = $this->draft();

        $this->actingAs(User::factory()->create())
            ->post('/drafts/'.$draft->id.'/video', [
                'video' => $this->video(),
                'description' => 'Not mine',
            ])->assertForbidden();

        $this->assertDatabaseCount('post_videos', 0);
    }

    public function test_upload_rejects_a_file_above_the_configured_limit(): void
    {
        config(['media.video.enabled' => true, 'media.video.max_input_kilobytes' => 64]);
        [$author, $draft] = $this->draft();

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => UploadedFile::fake()->create('too-large.mp4', 65, 'video/mp4'),
            'description' => 'Too large',
        ])->assertSessionHasErrors('video');

        $this->assertDatabaseCount('post_videos', 0);
    }

    public function test_upload_requires_a_text_description(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => '   ',
        ])->assertSessionHasErrors('description');

        $this->assertDatabaseCount('post_videos', 0);
    }

    public function test_space_quota_counts_pending_reservations(): void
    {
        config(['media.video.enabled' => true, 'media.video.space_quota_bytes' => 1024 * 1024]);
        [$author, $draft] = $this->draft();
        $secondDraft = Post::factory()->for($draft->space)->for($author, 'author')->create([
            'published_at' => null,
        ]);

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'First video',
        ])->assertSessionHasNoErrors();

        $this->actingAs($author)->post('/drafts/'.$secondDraft->id.'/video', [
            'video' => $this->video(),
            'description' => 'Second video',
        ])->assertSessionHasErrors('video');

        $this->assertDatabaseCount('post_videos', 1);
    }

    public function test_video_cannot_be_added_to_an_image_draft(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();
        $draft->mediaItems()->create([
            'position' => 0,
            'disk' => 'media',
            'path' => 'posts/example.webp',
            'mime_type' => 'image/webp',
            'width' => 100,
            'height' => 100,
            'size_bytes' => 32,
            'checksum' => str_repeat('a', 64),
            'alt_text' => 'Existing image',
        ]);

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'A competing video',
        ])->assertSessionHasErrors('video');

        $this->assertDatabaseCount('post_videos', 0);
        $this->assertDatabaseCount('post_media', 1);
    }

    public function test_video_cannot_be_added_to_a_poll_draft(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();
        $draft->poll()->create(['question' => 'Pick a topic']);

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'A competing video',
        ])->assertSessionHasErrors('video');

        $this->assertDatabaseCount('post_videos', 0);
    }

    public function test_image_cannot_be_added_after_a_draft_video(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();
        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'Video first',
        ])->assertSessionHasNoErrors();

        $this->actingAs($author)->patch(route('drafts.update', $draft), [
            'space' => $draft->space->slug,
            'body' => 'A draft video post',
            'image' => UploadedFile::fake()->image('picture.jpg', 100, 100),
            'image_alt' => 'A picture',
        ])->assertSessionHasErrors('video');

        $this->assertDatabaseCount('post_media', 0);
        $this->assertDatabaseCount('post_videos', 1);
    }

    public function test_ready_video_draft_can_be_published(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();
        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'Ready to publish',
        ])->assertSessionHasNoErrors();
        $draft->fresh()->video?->update(['status' => PostVideo::STATUS_READY]);

        $this->actingAs($author)->post(route('drafts.publish', $draft), [
            'space' => $draft->space->slug,
            'body' => 'A draft video post',
        ])->assertSessionHasNoErrors()->assertRedirect(route('posts.show', $draft));

        $this->assertNotNull($draft->fresh()->published_at);
    }

    public function test_author_can_replace_and_remove_a_pending_draft_video(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'First version',
        ])->assertSessionHasNoErrors();
        $oldPath = $draft->fresh()->video?->source_path;
        $this->assertNotNull($oldPath);

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => UploadedFile::fake()->create('replacement.mp4', 512, 'video/mp4'),
            'description' => 'Second version',
        ])->assertSessionHasNoErrors();

        $video = $draft->fresh()->video;
        $this->assertInstanceOf(PostVideo::class, $video);
        $this->assertSame(512 * 1024, $video->reserved_bytes);
        $this->assertNotSame($oldPath, $video->source_path);
        Storage::disk('media')->assertMissing($oldPath);
        Storage::disk('media')->assertExists($video->source_path);

        $this->actingAs($author)->delete('/drafts/'.$draft->id.'/video')
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('post_videos', 0);
        Storage::disk('media')->assertMissing($video->source_path);
    }

    public function test_deleting_a_draft_removes_its_private_video_source(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();
        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'Temporary draft',
        ])->assertSessionHasNoErrors();
        $path = $draft->fresh()->video?->source_path;
        $this->assertNotNull($path);

        $this->actingAs($author)->delete(route('drafts.destroy', $draft))->assertRedirect();

        $this->assertDatabaseMissing('post_videos', ['post_id' => $draft->id]);
        Storage::disk('media')->assertMissing($path);
    }

    public function test_moving_a_video_draft_obeys_the_destination_space_quota(): void
    {
        config(['media.video.enabled' => true, 'media.video.space_quota_bytes' => 1024 * 1024]);
        [$author, $draft] = $this->draft();
        $otherSpace = Space::factory()->for($author, 'owner')->create();
        $otherDraft = Post::factory()->for($otherSpace)->for($author, 'author')->create([
            'published_at' => null,
        ]);
        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'A video to move',
        ])->assertSessionHasNoErrors();
        $this->actingAs($author)->post('/drafts/'.$otherDraft->id.'/video', [
            'video' => $this->video(),
            'description' => 'Destination reservation',
        ])->assertSessionHasNoErrors();

        $this->actingAs($author)->patch(route('drafts.update', $draft), [
            'space' => $otherSpace->slug,
            'body' => 'A draft video post',
        ])->assertSessionHasErrors('video');

        $this->assertSame($draft->space_id, $draft->fresh()->space_id);
        $this->assertSame($draft->space_id, $draft->fresh()->video?->space_id);
    }

    public function test_moving_a_video_draft_updates_its_quota_space(): void
    {
        config(['media.video.enabled' => true]);
        [$author, $draft] = $this->draft();
        $otherSpace = Space::factory()->for($author, 'owner')->create();
        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $this->video(),
            'description' => 'A video to move',
        ])->assertSessionHasNoErrors();

        $this->actingAs($author)->patch(route('drafts.update', $draft), [
            'space' => $otherSpace->slug,
            'body' => 'A draft video post',
        ])->assertSessionHasNoErrors();

        $this->assertSame($otherSpace->id, $draft->fresh()->space_id);
        $this->assertSame($otherSpace->id, $draft->fresh()->video?->space_id);
    }

    /** @return array{User, Post} */
    private function draft(): array
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $draft = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'A draft video post',
            'published_at' => null,
        ]);

        return [$author, $draft];
    }

    private function video(): UploadedFile
    {
        return UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4');
    }
}
