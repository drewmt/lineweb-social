<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostVideoDatabaseQueueTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
    }

    public function test_database_worker_processes_media_job_from_draft_upload(): void
    {
        Storage::fake('media');
        config([
            'media.disk' => 'media',
            'media.video.enabled' => true,
            'queue.default' => 'database',
        ]);
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $draft = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'Database queue video test',
            'published_at' => null,
        ]);

        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => UploadedFile::fake()->createWithContent('invalid.mp4', 'invalid video bytes'),
            'description' => 'Invalid test video',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work database --once --queue=media --tries=1')
            ->assertSuccessful();

        $this->assertSame(PostVideo::STATUS_FAILED, $draft->fresh()->video?->status);
        $this->assertDatabaseCount('jobs', 0);
    }
}
