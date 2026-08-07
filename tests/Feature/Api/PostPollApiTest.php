<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPollApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_exposes_only_aggregate_poll_state_after_the_viewer_votes(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($viewer);
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $poll = $post->poll()->create([
            'question' => 'Which starter should we ship?',
            'closes_after_days' => 3,
            'closes_at' => now()->addDays(3),
        ]);
        $first = $poll->options()->create(['position' => 0, 'label' => 'Community hub']);
        $second = $poll->options()->create(['position' => 1, 'label' => 'Creator space']);

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.posts.show', $post))
            ->assertOk()
            ->assertJsonPath('data.poll.question', $poll->question)
            ->assertJsonPath('data.poll.show_results', false)
            ->assertJsonPath('data.poll.options.0.votes', null)
            ->assertJsonPath('data.poll.viewer_option_id', null);

        $this->actingAs($viewer)
            ->put(route('posts.poll-votes.store', $post), ['option_id' => $second->getKey()])
            ->assertRedirect();

        $this->app['auth']->forgetGuards();
        $response = $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.posts.show', $post));

        $response
            ->assertOk()
            ->assertJsonPath('data.poll.show_results', true)
            ->assertJsonPath('data.poll.total_votes', 1)
            ->assertJsonPath('data.poll.options.0.votes', 0)
            ->assertJsonPath('data.poll.options.1.votes', 1)
            ->assertJsonPath('data.poll.options.1.percentage', 100)
            ->assertJsonPath('data.poll.viewer_option_id', (string) $second->getKey())
            ->assertJsonMissing(['user_id' => $viewer->getKey()])
            ->assertJsonMissing(['voter' => $viewer->getKey()]);

        $this->assertNotSame($first->getKey(), $second->getKey());
    }

    private function token(User $user): string
    {
        return $user->createToken('Post poll API test', ['feed:read'])->plainTextToken;
    }
}
