<?php

namespace Tests\Feature\Api;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\SpaceModerationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_returns_safe_projection_with_cursor_and_filters(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($viewer, 'owner')->create();
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create();

        $this->travelTo(now()->subMinute());
        $viewer->notify(new CommentReplyNotification($comment));

        $this->travel(1)->second();
        $report = PostReport::factory()->create([
            'post_id' => $post->getKey(),
            'reporter_id' => $author->getKey(),
        ]);
        $viewer->notify(new SpaceModerationNotification($space->getKey(), 'post', $report->getKey()));

        $ordered = DatabaseNotification::query()
            ->where('notifiable_type', $viewer->getMorphClass())
            ->where('notifiable_id', $viewer->getKey())
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        $orderedIds = $ordered->pluck('id')->all();

        $firstUnread = $ordered->first();
        $secondUnread = $ordered->get(1);

        $this->assertNotNull($firstUnread);
        $this->assertNotNull($secondUnread);

        $pageOne = $this->withToken($this->token($viewer))->getJson(route('api.v1.notifications', ['limit' => 1]));

        $pageOne
            ->assertOk()
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('data.0.id', (string) $firstUnread->getKey())
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.0.title', fn (string $title) => is_string($title) && $title !== '')
            ->assertJsonPath('data.0.target', fn ($target) => is_array($target))
            ->assertJsonPath('data.0.target.type', 'space_moderation')
            ->assertJsonPath('data.0.target.space_slug', $space->slug)
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('links.next', fn (?string $url): bool => is_string($url) && str_contains($url, 'cursor='));

        $cursor = $pageOne->json('meta.next_cursor');
        $this->assertIsString($cursor);

        $pageTwo = $this->withToken($this->token($viewer))->getJson(route('api.v1.notifications', [
            'limit' => 1,
            'cursor' => $cursor,
        ]));

        $pageTwo
            ->assertOk()
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('data.0.id', (string) $secondUnread->getKey())
            ->assertJsonPath('data.0.target.type', 'post')
            ->assertJsonPath('data.0.target.post_id', (string) $post->getKey())
            ->assertJsonPath('data.0.target.comment_id', (string) $comment->getKey())
            ->assertJsonPath('links.next', null);

        $firstUnread->update(['read_at' => now()]);

        $unread = $this->withToken($this->token($viewer))->getJson(route('api.v1.notifications', ['filter' => 'unread']));

        $unread
            ->assertOk()
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $secondUnread->getKey());

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.notifications', ['cursor' => $cursor.'tampered']))
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        $otherViewer = User::factory()->create();
        $this->withToken($this->token($otherViewer))
            ->getJson(route('api.v1.notifications', ['cursor' => $cursor]))
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.notifications', ['cursor' => $cursor, 'filter' => 'unread']))
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');
    }

    public function test_notifications_write_endpoints_mark_one_and_all_read(): void
    {
        $viewer = User::factory()->create();
        $otherViewer = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($viewer, 'owner')->create();
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create();

        $viewer->notify(new CommentReplyNotification($comment));
        $viewer->notify(new CommentReplyNotification($comment));

        $ordered = DatabaseNotification::query()
            ->where('notifiable_type', $viewer->getMorphClass())
            ->where('notifiable_id', $viewer->getKey())
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $latest = $ordered->first();
        $this->assertNotNull($latest);

        $this->withToken($this->token($viewer))
            ->patchJson(route('api.v1.notifications.read', ['notification' => $latest->getKey()]))
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden');

        $this->withToken($this->token($otherViewer, ['notifications:write']))
            ->patchJson(route('api.v1.notifications.read', ['notification' => $latest->getKey()]))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $writeToken = $this->token($viewer, ['notifications:write']);
        $this->withToken($writeToken)
            ->patchJson(route('api.v1.notifications.read', ['notification' => $latest->getKey()]))
            ->assertStatus(204);

        $this->assertNotNull($latest->refresh()->read_at);

        $this->withToken($writeToken)
            ->patchJson(route('api.v1.notifications.read-all'))
            ->assertStatus(204);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $viewer->getKey(),
            'read_at' => null,
        ]);
    }

    public function test_notifications_require_scope_and_verified_access(): void
    {
        $viewer = User::factory()->create();
        $unverified = User::factory()->unverified()->create();

        $this->withToken($this->token($viewer, ['profile:read']))
            ->getJson(route('api.v1.notifications'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->withToken($this->token($unverified))
            ->getJson(route('api.v1.notifications'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->withoutToken()
            ->getJson(route('api.v1.notifications'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->withToken($this->token($viewer, ['notifications:read']))
            ->getJson(route('api.v1.notifications', ['filter' => 'maybe']))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed');
    }

    /** @param list<string> $abilities */
    private function token(User $user, array $abilities = ['notifications:read']): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('Notification API test', $abilities, now()->addDays(30))->plainTextToken;
    }
}
