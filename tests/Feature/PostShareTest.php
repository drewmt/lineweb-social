<?php

namespace Tests\Feature;

use App\Events\PostPublished;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_repost_and_update_their_single_share_with_a_quote(): void
    {
        Event::fake([PostPublished::class]);

        $author = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($member);
        $source = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'An original post worth discussing.',
        ]);

        $this->actingAs($member)
            ->post(route('posts.shares.store', $source), ['body' => ''])
            ->assertRedirect()
            ->assertSessionHas('status', 'Post reposted.');

        $share = Post::query()
            ->where('user_id', $member->getKey())
            ->where('shared_post_id', $source->getKey())
            ->firstOrFail();

        $this->assertSame('', $share->body);
        $this->assertSame($space->getKey(), $share->space_id);
        Event::assertDispatchedTimes(PostPublished::class, 1);

        $this->actingAs($member)
            ->post(route('posts.shares.store', $source), [
                'body' => '  This is exactly the trade-off we need to discuss.  ',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Quote post published.');

        $share->refresh();

        $this->assertSame('This is exactly the trade-off we need to discuss.', $share->body);
        $this->assertNotNull($share->edited_at);
        $this->assertDatabaseCount('posts', 2);
        Event::assertDispatchedTimes(PostPublished::class, 1);
    }

    public function test_sharing_requires_membership_a_visible_original_and_a_non_shared_source(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($member);
        $source = Post::factory()->for($space)->for($author, 'author')->create();

        $this->actingAs($outsider)
            ->post(route('posts.shares.store', $source), ['body' => 'No membership'])
            ->assertForbidden();

        $member->outgoingRelationships()->create([
            'target_id' => $author->getKey(),
            'type' => 'block',
        ]);

        $this->actingAs($member)
            ->post(route('posts.shares.store', $source), ['body' => 'Blocked source'])
            ->assertForbidden();

        $member->outgoingRelationships()->delete();
        $sharedSource = Post::factory()->for($space)->for($author, 'author')->create([
            'shared_post_id' => $source->getKey(),
            'body' => 'A quote of the original post.',
        ]);

        $this->actingAs($member)
            ->post(route('posts.shares.store', $sharedSource), ['body' => 'Nested share'])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('posts.shares.store', $source), ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');
    }

    public function test_feed_permalink_and_api_expose_the_source_only_when_it_is_still_visible(): void
    {
        $author = User::factory()->create(['name' => 'Original Author', 'handle' => 'original-author']);
        $member = User::factory()->create(['name' => 'Sharing Member', 'handle' => 'sharing-member']);
        $viewer = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create(['name' => 'Design Circle']);
        $space->addMember($member);
        $space->addMember($viewer);
        $source = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'Original source that remains attributable.',
            'published_at' => now()->subMinute(),
        ]);
        $share = Post::factory()->for($space)->for($member, 'author')->create([
            'body' => 'A useful perspective on the original.',
            'shared_post_id' => $source->getKey(),
            'published_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', $share->getKey())
                ->where('posts.0.share.source.id', $source->getKey())
                ->where('posts.0.share.source.author.handle', 'original-author')
                ->where('posts.0.canShare', false)
                ->where('posts.1.id', $source->getKey())
                ->where('posts.1.canShare', true));

        $this->actingAs($viewer)
            ->get(route('posts.show', $share))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.share.source.id', $source->getKey())
                ->where('post.canShare', false));

        $this->app['auth']->forgetGuards();
        $token = $viewer->createToken('Post share API test', ['feed:read'])->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->getJson(route('api.v1.posts.show', $share))
            ->assertOk()
            ->assertJsonPath('data.share.source.id', (string) $source->getKey())
            ->assertJsonPath('data.share.source.author.handle', 'original-author')
            ->assertJsonPath('data.viewer.can_share', false);

        $source->update(['hidden_at' => now()]);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', $share->getKey())
                ->where('posts.0.body', 'A useful perspective on the original.')
                ->where('posts.0.share', null));

        $this->app['auth']->forgetGuards();
        $hiddenToken = $viewer->createToken('Post share API test hidden', ['feed:read'])->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer '.$hiddenToken,
            'Accept' => 'application/json',
        ])
            ->getJson(route('api.v1.posts.show', $share))
            ->assertOk()
            ->assertJsonPath('data.share', null);
    }

    public function test_source_deletion_nulls_the_link_and_hides_empty_reposts(): void
    {
        $author = User::factory()->create();
        $reposter = User::factory()->create();
        $viewer = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($reposter);
        $space->addMember($viewer);
        $source = Post::factory()->for($space)->for($author, 'author')->create();
        $repost = Post::factory()->for($space)->for($reposter, 'author')->create([
            'body' => '',
            'shared_post_id' => $source->getKey(),
        ]);

        $source->delete();
        $repost->refresh();

        $this->assertNull($repost->shared_post_id);
        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts', 0));
        $this->actingAs($viewer)
            ->get(route('posts.show', $repost))
            ->assertForbidden();
    }
}
