<?php

namespace Tests\Feature\Api;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_detail_returns_the_safe_visible_projection_and_viewer_state(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create([
            'name' => 'Post Author',
            'headline' => 'Platform architect',
            'profile_visibility' => ProfileVisibility::Members,
        ]);
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($viewer);
        $post = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'A stable post for API readers.',
        ]);

        Storage::fake('media');
        $mediaPath = 'posts/post-media.webp';
        $mediaContents = 'api-safe-webp';
        Storage::disk('media')->put($mediaPath, $mediaContents);
        $media = $post->media()->create([
            'disk' => 'media',
            'path' => $mediaPath,
            'mime_type' => 'image/webp',
            'width' => 1280,
            'height' => 720,
            'size_bytes' => strlen($mediaContents),
            'checksum' => hash('sha256', $mediaContents),
            'alt_text' => 'A clean API image.',
        ]);

        Comment::factory()->for($post)->for($viewer, 'author')->create(['body' => 'Visible comment']);
        $muted = User::factory()->create();
        $blocking = User::factory()->create();

        $viewer->outgoingRelationships()->create([
            'target_id' => $muted->getKey(),
            'type' => UserRelationshipType::Mute->value,
        ]);
        $viewer->outgoingRelationships()->create([
            'target_id' => $blocking->getKey(),
            'type' => UserRelationshipType::Block->value,
        ]);

        Comment::factory()->for($post)->for($muted, 'author')->create(['body' => 'Muted comment']);
        Comment::factory()->for($post)->for($blocking, 'author')->create(['body' => 'Blocking comment']);

        PostReport::factory()->create([
            'post_id' => $post->getKey(),
            'reporter_id' => $viewer->getKey(),
        ]);

        $response = $this->getWithToken($viewer)
            ->getJson(route('api.v1.posts.show', $post));

        $response
            ->assertOk()
            ->assertJsonPath('data.id', (string) $post->getKey())
            ->assertJsonPath('data.body', 'A stable post for API readers.')
            ->assertJsonPath('data.comments_count', 1)
            ->assertJsonPath('data.author.profile_visible', true)
            ->assertJsonPath('data.space.slug', $space->slug)
            ->assertJsonPath('data.viewer.can_comment', true)
            ->assertJsonPath('data.viewer.can_report', true)
            ->assertJsonPath('data.viewer.has_reported', true)
            ->assertJsonPath('data.media.url', route('api.v1.posts.media', $post))
            ->assertJsonPath('data.media.alt', $media->alt_text)
            ->assertJsonMissingPath('data.media.disk')
            ->assertJsonMissingPath('data.media.path')
            ->assertJsonMissingPath('data.media.checksum')
            ->assertJsonMissingPath('data.media.size_bytes')
            ->assertJsonMissingPath('data.media.owner_id')
            ->assertJsonMissingPath('data.media.author_id');

        $this->assertSame(
            ['id', 'body', 'published_at', 'edited_at', 'media', 'comments_count', 'author', 'space', 'viewer'],
            array_keys($response->json('data')),
        );
        $this->assertSame(
            ['handle', 'name', 'headline', 'profile_visible'],
            array_keys($response->json('data.author')),
        );
    }

    public function test_post_endpoints_require_scope_and_visibility_boundaries(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $public = Space::factory()->create();
        $private = Space::factory()->private()->create();
        $publicPost = Post::factory()->for($public)->for($author, 'author')->create();
        $privatePost = Post::factory()->for($private)->for($author, 'author')->create();
        $hiddenPost = Post::factory()->for($public)->for($author, 'author')->create([
            'hidden_at' => now(),
            'body' => 'Moderated post',
        ]);
        $draftPost = Post::factory()->for($public)->for($author, 'author')->create([
            'published_at' => null,
            'body' => 'Draft post',
        ]);

        $this->getWithToken($viewer, ['spaces:read'])
            ->getJson(route('api.v1.posts.show', $publicPost))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $privateResponse = $this->getWithToken($viewer)
            ->getJson(route('api.v1.posts.show', $privatePost));
        $privateResponse
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->getWithToken($viewer)
            ->getJson(route('api.v1.posts.show', $hiddenPost))
            ->assertNotFound();

        $this->getWithToken($viewer)
            ->getJson(route('api.v1.posts.show', $draftPost))
            ->assertNotFound();

        $this->getWithToken($viewer, ['profile:read'])
            ->getJson(route('api.v1.posts.show', $publicPost))
            ->assertForbidden();

        $this->getWithToken($viewer)
            ->getJson(route('api.v1.posts.show', 999999))
            ->assertNotFound();
    }

    public function test_post_comments_are_visible_in_chronological_pages_and_filter_muted_blocked_hidden_comments(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $mutedAuthor = User::factory()->create();
        $blockingAuthor = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($viewer);
        $post = Post::factory()->for($space)->for($author, 'author')->create();

        Comment::factory()->for($post)->for($author, 'author')->create([
            'body' => 'Visible 1',
            'published_at' => now()->subMinutes(3),
        ]);
        $reported = Comment::factory()->for($post)->for($author, 'author')->create([
            'body' => 'Visible 2',
            'published_at' => now()->subMinutes(2),
        ]);
        Comment::factory()->for($post)->for($author, 'author')->create([
            'body' => 'Visible 3',
            'published_at' => now()->subMinutes(1),
        ]);
        Comment::factory()->for($post)->for($mutedAuthor, 'author')->create([
            'body' => 'Muted comment',
            'published_at' => now(),
        ]);
        Comment::factory()->for($post)->for($blockingAuthor, 'author')->create([
            'body' => 'Blocking comment',
            'published_at' => now()->addMinute(),
        ]);
        Comment::factory()->for($post)->for($author, 'author')->create([
            'body' => 'Hidden comment',
            'published_at' => now()->addMinutes(2),
            'hidden_at' => now(),
        ]);

        CommentReport::factory()->create([
            'comment_id' => $reported->getKey(),
            'reporter_id' => $viewer->getKey(),
        ]);

        $viewer->outgoingRelationships()->create([
            'target_id' => $mutedAuthor->getKey(),
            'type' => UserRelationshipType::Mute->value,
        ]);
        $blockingAuthor->outgoingRelationships()->create([
            'target_id' => $viewer->getKey(),
            'type' => UserRelationshipType::Block->value,
        ]);

        $pageOne = $this->getWithToken($viewer)
            ->getJson(route('api.v1.posts.comments', [
                'post' => $post,
                'limit' => 2,
            ]));

        $pageOne
            ->assertOk()
            ->assertJsonPath('meta.limit', 2)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('links.next', fn (?string $url): bool => is_string($url) && str_contains($url, 'cursor='))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', (string) $post->comments()->orderBy('published_at')->first()->getKey())
            ->assertJsonPath('data.1.id', (string) $reported->getKey())
            ->assertJsonPath('data.1.viewer.can_report', true)
            ->assertJsonPath('data.1.viewer.has_reported', true)
            ->assertJsonMissing(['body' => 'Muted comment'])
            ->assertJsonMissing(['body' => 'Blocking comment'])
            ->assertJsonMissing(['body' => 'Hidden comment']);

        $cursor = $pageOne->json('meta.next_cursor');
        $this->assertIsString($cursor);

        $pageTwo = $this->getWithToken($viewer)
            ->getJson(route('api.v1.posts.comments', [
                'post' => $post,
                'limit' => 2,
                'cursor' => $cursor,
            ]));

        $pageTwo
            ->assertOk()
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) Comment::query()
                ->where('post_id', $post->getKey())
                ->where('body', 'Visible 3')
                ->firstOrFail()
                ->getKey())
            ->assertJsonPath('links.next', null);

        $tampered = $cursor.'tampered';
        $invalidCursorResponse = $this->getWithToken($viewer)
            ->getJson(route('api.v1.posts.comments', [
                'post' => $post,
                'cursor' => $tampered,
            ]));
        $invalidCursorResponse
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        $otherViewer = User::factory()->create();
        $invalidViewerResponse = $this->getWithToken($otherViewer)
            ->getJson(route('api.v1.posts.comments', [
                'post' => $post,
                'cursor' => $cursor,
            ]));
        $invalidViewerResponse
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        foreach ([0, 51] as $limit) {
            $this->getWithToken($viewer)
                ->getJson(route('api.v1.posts.comments', [
                    'post' => $post,
                    'limit' => $limit,
                ]))
                ->assertUnprocessable()
                ->assertJsonPath('code', 'validation_failed');
        }
    }

    /** @param list<string> $abilities */
    private function getWithToken(User $user, array $abilities = ['feed:read']): self
    {
        return $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token($user, $abilities),
                'Accept' => 'application/json',
            ]);
    }

    private function token(User $user, array $abilities = ['feed:read']): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('Post API test', $abilities, now()->addDays(30))->plainTextToken;
    }
}
