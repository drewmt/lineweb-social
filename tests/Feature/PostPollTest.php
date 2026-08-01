<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostPollTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_publish_a_poll_vote_and_change_their_vote(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($member);

        $this->actingAs($author)
            ->post(route('spaces.posts.store', $space), [
                'body' => '',
                'poll_question' => 'Which community event should we host next?',
                'poll_options' => ['Design review', 'Open source night', 'Local meetup'],
                'poll_duration' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Post published.');

        $post = Post::query()->latest('id')->firstOrFail();
        $poll = $post->poll()->with('options')->firstOrFail();

        $this->assertSame('Which community event should we host next?', $poll->question);
        $this->assertSame(3, $poll->closes_after_days);
        $this->assertNotNull($poll->closes_at);
        $this->assertCount(3, $poll->options);

        $this->actingAs($member)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', $post->getKey())
                ->where('posts.0.body', '')
                ->where('posts.0.poll.question', $poll->question));

        $firstOption = $poll->options->firstOrFail();
        $secondOption = $poll->options->get(1);

        $this->actingAs($member)
            ->put(route('posts.poll-votes.store', $post), ['option_id' => $firstOption->getKey()])
            ->assertRedirect()
            ->assertSessionHas('status', 'Vote recorded.');

        $this->assertDatabaseHas('post_poll_votes', [
            'post_poll_id' => $poll->getKey(),
            'post_poll_option_id' => $firstOption->getKey(),
            'user_id' => $member->getKey(),
        ]);

        $this->actingAs($author)
            ->patch(route('posts.update', $post), ['body' => 'Changed context'])
            ->assertForbidden();

        $this->actingAs($member)
            ->put(route('posts.poll-votes.store', $post), ['option_id' => $secondOption?->getKey()])
            ->assertRedirect()
            ->assertSessionHas('status', 'Vote updated.');

        $this->assertDatabaseCount('post_poll_votes', 1);
        $this->assertDatabaseHas('post_poll_votes', [
            'post_poll_id' => $poll->getKey(),
            'post_poll_option_id' => $secondOption?->getKey(),
            'user_id' => $member->getKey(),
        ]);
    }

    public function test_poll_validation_requires_distinct_questioned_options_or_normal_post_content(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();

        $this->actingAs($author)
            ->from(route('feed'))
            ->post(route('spaces.posts.store', $space), [
                'body' => '',
            ])
            ->assertRedirect(route('feed'))
            ->assertSessionHasErrors('body');

        $this->actingAs($author)
            ->from(route('feed'))
            ->post(route('spaces.posts.store', $space), [
                'body' => '',
                'poll_question' => 'Pick one',
                'poll_options' => ['Same option', 'same option'],
                'poll_duration' => 2,
            ])
            ->assertRedirect(route('feed'))
            ->assertSessionHasErrors(['poll_options', 'poll_duration']);

        $this->actingAs($author)
            ->from(route('feed'))
            ->post(route('spaces.posts.store', $space), [
                'body' => '',
                'poll_question' => '',
                'poll_options' => ['One', 'Two'],
            ])
            ->assertRedirect(route('feed'))
            ->assertSessionHasErrors('poll_question');
    }

    public function test_poll_results_are_hidden_until_a_member_votes_or_the_poll_closes(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($member);
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $poll = $post->poll()->create([
            'question' => 'Which documentation should we improve?',
            'closes_after_days' => 1,
            'closes_at' => now()->addDay(),
        ]);
        $first = $poll->options()->create(['position' => 0, 'label' => 'Getting started']);
        $second = $poll->options()->create(['position' => 1, 'label' => 'Deployment']);

        $this->actingAs($member)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.poll.question', $poll->question)
                ->where('posts.0.poll.showResults', false)
                ->where('posts.0.poll.options.0.votes', null)
                ->where('posts.0.poll.canVote', true));

        $this->actingAs($member)
            ->put(route('posts.poll-votes.store', $post), ['option_id' => $first->getKey()])
            ->assertRedirect();

        $this->actingAs($member)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.poll.showResults', true)
                ->where('posts.0.poll.totalVotes', 1)
                ->where('posts.0.poll.options.0.votes', 1)
                ->where('posts.0.poll.options.1.votes', 0)
                ->where('posts.0.poll.viewerOptionId', $first->getKey()));

        $poll->update(['closes_at' => now()->subSecond()]);

        $this->actingAs($member)
            ->put(route('posts.poll-votes.store', $post), ['option_id' => $second->getKey()])
            ->assertForbidden();
    }

    public function test_non_members_cannot_vote_and_drafts_only_start_the_timer_when_published(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();

        $this->actingAs($author)
            ->post(route('drafts.store'), [
                'body' => '',
                'space' => $space->slug,
                'poll_question' => 'Which release note matters most?',
                'poll_options' => ['Accessibility', 'Performance'],
                'poll_duration' => 1,
            ])
            ->assertRedirect();

        $draft = Post::query()->latest('id')->firstOrFail();
        $poll = $draft->poll()->with('options')->firstOrFail();
        $this->assertNull($poll->closes_at);

        $this->actingAs($outsider)
            ->put(route('posts.poll-votes.store', $draft), [
                'option_id' => $poll->options->firstOrFail()->getKey(),
            ])
            ->assertForbidden();

        $this->actingAs($author)
            ->post(route('drafts.publish', $draft), [
                'body' => '',
                'space' => $space->slug,
                'poll_question' => $poll->question,
                'poll_options' => $poll->options->pluck('label')->all(),
                'poll_duration' => 1,
            ])
            ->assertRedirect(route('posts.show', $draft));

        $this->assertNotNull($poll->fresh()->closes_at);
    }
}
