<?php

namespace Tests\Feature\Api;

use App\Enums\PostReactionType;
use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    public function test_feed_returns_only_policy_visible_posts_in_the_safe_public_contract(): void
    {
        $viewer = User::factory()->create();
        $publicAuthor = User::factory()->create([
            'headline' => 'Public-space author',
            'profile_visibility' => ProfileVisibility::Private,
        ]);
        $privateAuthor = User::factory()->create([
            'headline' => 'Member-space author',
            'profile_visibility' => ProfileVisibility::Members,
        ]);
        $mutedAuthor = User::factory()->create();
        $blockingAuthor = User::factory()->create();
        $visibleCommenter = User::factory()->create();
        $mutedCommenter = User::factory()->create();
        $public = Space::factory()->for($publicAuthor, 'owner')->create();
        $private = Space::factory()->for($privateAuthor, 'owner')->private()->create();
        $inaccessible = Space::factory()->private()->create();
        $private->addMember($viewer);

        $olderPublic = Post::factory()->for($public)->for($publicAuthor, 'author')->create([
            'body' => 'Older public post',
            'published_at' => now()->subMinutes(2),
        ]);
        $newerPrivate = Post::factory()->for($private)->for($privateAuthor, 'author')->create([
            'body' => 'Newer private-member post',
            'published_at' => now()->subMinute(),
        ]);

        Comment::factory()->for($olderPublic)->for($visibleCommenter, 'author')->create();
        Comment::factory()->for($olderPublic)->for($mutedCommenter, 'author')->create();
        Comment::factory()->for($olderPublic)->for($visibleCommenter, 'author')->create([
            'hidden_at' => now(),
        ]);
        PostReport::factory()->create([
            'post_id' => $olderPublic->getKey(),
            'reporter_id' => $viewer->getKey(),
        ]);
        PostReaction::query()->create([
            'post_id' => $olderPublic->getKey(),
            'user_id' => $viewer->getKey(),
            'type' => PostReactionType::Insightful,
        ]);

        Post::factory()->for($public)->for($publicAuthor, 'author')->create([
            'body' => 'Draft post',
            'published_at' => null,
        ]);
        Post::factory()->for($public)->for($publicAuthor, 'author')->create([
            'body' => 'Moderated post',
            'hidden_at' => now(),
        ]);
        Post::factory()->for($inaccessible)->create(['body' => 'Private outsider post']);
        Post::factory()->for($public)->for($mutedAuthor, 'author')->create(['body' => 'Muted post']);
        Post::factory()->for($public)->for($blockingAuthor, 'author')->create(['body' => 'Blocking post']);

        $viewer->outgoingRelationships()->create([
            'target_id' => $mutedAuthor->getKey(),
            'type' => UserRelationshipType::Mute,
        ]);
        $viewer->outgoingRelationships()->create([
            'target_id' => $mutedCommenter->getKey(),
            'type' => UserRelationshipType::Mute,
        ]);
        $blockingAuthor->outgoingRelationships()->create([
            'target_id' => $viewer->getKey(),
            'type' => UserRelationshipType::Block,
        ]);

        $response = $this->withToken($this->token($viewer))->getJson(route('api.v1.feed'));

        $response
            ->assertOk()
            ->assertJsonPath('meta.limit', 20)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('links.next', null)
            ->assertJsonPath('data.0.id', (string) $newerPrivate->getKey())
            ->assertJsonPath('data.0.viewer.can_comment', true)
            ->assertJsonPath('data.0.author.profile_visible', true)
            ->assertJsonPath('data.0.space.slug', $private->slug)
            ->assertJsonPath('data.0.space.viewer.is_member', true)
            ->assertJsonPath('data.1.id', (string) $olderPublic->getKey())
            ->assertJsonPath('data.1.comments_count', 1)
            ->assertJsonPath('data.1.reactions.total', 1)
            ->assertJsonPath('data.1.reactions.counts.insightful', 1)
            ->assertJsonPath('data.1.viewer.reaction_type', PostReactionType::Insightful->value)
            ->assertJsonPath('data.1.author.profile_visible', false)
            ->assertJsonPath('data.1.viewer.can_comment', false)
            ->assertJsonPath('data.1.viewer.can_report', true)
            ->assertJsonPath('data.1.viewer.has_reported', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['body' => 'Draft post'])
            ->assertJsonMissing(['body' => 'Moderated post'])
            ->assertJsonMissing(['body' => 'Private outsider post'])
            ->assertJsonMissing(['body' => 'Muted post'])
            ->assertJsonMissing(['body' => 'Blocking post'])
            ->assertJsonMissingPath('data.0.hidden_at')
            ->assertJsonMissingPath('data.0.moderation_note')
            ->assertJsonMissingPath('data.0.author.id')
            ->assertHeader('X-RateLimit-Limit', '120');

        $this->assertSame(
            ['id', 'body', 'published_at', 'edited_at', 'media', 'comments_count', 'reactions', 'author', 'space', 'viewer'],
            array_keys($response->json('data.0')),
        );
        $this->assertSame(
            ['handle', 'name', 'headline', 'profile_visible'],
            array_keys($response->json('data.0.author')),
        );
        $this->assertSame(
            ['can_comment', 'can_report', 'has_reported', 'can_react', 'reaction_type'],
            array_keys($response->json('data.0.viewer')),
        );
    }

    public function test_feed_cursor_is_deterministic_opaque_and_bound_to_viewer_and_filter(): void
    {
        $this->freezeTime();
        $viewer = User::factory()->create();
        $otherViewer = User::factory()->create();
        $author = User::factory()->create();
        $firstSpace = Space::factory()->for($author, 'owner')->create(['slug' => 'first-space']);
        $secondSpace = Space::factory()->for($author, 'owner')->create(['slug' => 'second-space']);
        $first = Post::factory()->for($firstSpace)->for($author, 'author')->create(['published_at' => now()]);
        $second = Post::factory()->for($firstSpace)->for($author, 'author')->create(['published_at' => now()]);
        $third = Post::factory()->for($firstSpace)->for($author, 'author')->create(['published_at' => now()]);

        $pageOne = $this->withToken($this->token($viewer))->getJson(route('api.v1.feed', [
            'space' => $firstSpace->slug,
            'limit' => 2,
        ]));

        $pageOne
            ->assertOk()
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('data.0.id', (string) $third->getKey())
            ->assertJsonPath('data.1.id', (string) $second->getKey());

        $cursor = $pageOne->json('meta.next_cursor');
        $this->assertIsString($cursor);
        $this->assertStringNotContainsString('viewer_id', $cursor);

        $pageTwo = $this->withToken($this->token($viewer))->getJson(route('api.v1.feed', [
            'space' => $firstSpace->slug,
            'limit' => 2,
            'cursor' => $cursor,
        ]));

        $pageTwo
            ->assertOk()
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('data.0.id', (string) $first->getKey())
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.feed', [
                'space' => $firstSpace->slug,
                'cursor' => $cursor.'tampered',
            ]))
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        $this->withToken($this->token($otherViewer))
            ->getJson(route('api.v1.feed', [
                'space' => $firstSpace->slug,
                'cursor' => $cursor,
            ]))
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.feed', [
                'space' => $secondSpace->slug,
                'cursor' => $cursor,
            ]))
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        foreach ([0, 51] as $limit) {
            $this->withToken($this->token($viewer))
                ->getJson(route('api.v1.feed', ['limit' => $limit]))
                ->assertUnprocessable()
                ->assertJsonPath('code', 'validation_failed');
        }
    }

    public function test_feed_requires_its_scope_and_hides_inaccessible_space_filters(): void
    {
        $viewer = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $public = Space::factory()->create(['slug' => 'visible-space']);
        $private = Space::factory()->private()->create(['slug' => 'private-space']);
        $visiblePost = Post::factory()->for($public)->create();
        Post::factory()->for($private)->create();

        $this->withToken($this->token($viewer, ['profile:read']))
            ->getJson(route('api.v1.feed'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->withToken($this->token($unverified))
            ->getJson(route('api.v1.feed'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.feed', ['space' => $private->slug]))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.feed', ['space' => $public->slug]))
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $visiblePost->getKey())
            ->assertJsonCount(1, 'data');
    }

    public function test_feed_media_uses_bearer_authorization_and_never_exposes_storage_metadata(): void
    {
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->private()->create();
        $space->addMember($viewer);
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $contents = 'safe-private-webp-contents';
        $path = 'posts/feed-image.webp';
        Storage::disk('media')->put($path, $contents);
        $media = $post->media()->create([
            'disk' => 'media',
            'path' => $path,
            'mime_type' => 'image/webp',
            'width' => 1280,
            'height' => 720,
            'size_bytes' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'alt_text' => 'A community workshop in progress.',
        ]);
        $token = $this->token($viewer);

        $feed = $this->withToken($token)->getJson(route('api.v1.feed'));

        $feed
            ->assertOk()
            ->assertJsonPath('data.0.media.url', route('api.v1.posts.media', $post))
            ->assertJsonPath('data.0.media.alt', 'A community workshop in progress.')
            ->assertJsonPath('data.0.media.mime_type', 'image/webp')
            ->assertJsonMissingPath('data.0.media.disk')
            ->assertJsonMissingPath('data.0.media.path')
            ->assertJsonMissingPath('data.0.media.checksum')
            ->assertJsonMissingPath('data.0.media.size_bytes');

        $response = $this->withToken($token)->get(route('api.v1.posts.media', $post));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Content-Disposition', 'inline; filename="post-image.webp"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-site')
            ->assertHeader('ETag', '"'.$media->checksum.'"')
            ->assertContent($contents);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Authorization', (string) $response->headers->get('Vary'));

        $this->withToken($this->token($viewer, ['profile:read']))
            ->get(route('api.v1.posts.media', $post))
            ->assertForbidden();

        $this->withToken($this->token($viewer, ['profile:read']))
            ->get(route('api.v1.posts.media', 999999))
            ->assertForbidden();

        $this->withToken($this->token($outsider))
            ->get(route('api.v1.posts.media', $post))
            ->assertNotFound();

        UserRelationship::query()->create([
            'actor_id' => $viewer->getKey(),
            'target_id' => $author->getKey(),
            'type' => UserRelationshipType::Mute,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->get(route('api.v1.posts.media', $post))
            ->assertNotFound();

        UserRelationship::query()->delete();
        Storage::disk('media')->delete($path);

        $this->withToken($token)
            ->get(route('api.v1.posts.media', $post))
            ->assertNotFound();
    }

    /** @param list<string> $abilities */
    private function token(User $user, array $abilities = ['feed:read']): string
    {
        // Sanctum's guard caches the authenticated user for the current process.
        // Feature tests make several simulated requests with different tokens.
        $this->app['auth']->forgetGuards();

        return $user->createToken('Feed API test', $abilities, now()->addDays(30))->plainTextToken;
    }
}
