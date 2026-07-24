<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_start_one_canonical_conversation_and_send_messages(): void
    {
        $andrew = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $maria = User::factory()->create([
            'name' => 'Maria',
            'profile_visibility' => ProfileVisibility::Public,
        ]);

        $this->actingAs($andrew)
            ->post(route('messages.start', $maria), ['body' => 'Hello Maria'])
            ->assertRedirect();

        $conversation = Conversation::query()->sole();

        $this->assertSame(
            [$andrew->getKey(), $maria->getKey()],
            [$conversation->user_one_id, $conversation->user_two_id],
        );
        $this->assertDatabaseHas('direct_messages', [
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $andrew->getKey(),
            'body' => 'Hello Maria',
        ]);

        $this->actingAs($maria)
            ->post(route('messages.start', $andrew), ['body' => 'Hi Andrew'])
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('direct_messages', 2);
    }

    public function test_starting_a_conversation_respects_profile_visibility_and_blocks(): void
    {
        $viewer = User::factory()->create();
        $private = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Private,
        ]);
        $shared = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Members,
        ]);
        $space = Space::factory()->private()->create();
        $space->addMember($viewer);
        $space->addMember($shared);

        $this->actingAs($viewer)
            ->get(route('messages.compose', $private))
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('messages.start', $private), ['body' => 'Not allowed'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('messages.compose', $shared))
            ->assertOk();

        $this->actingAs($shared)
            ->post(route('people.block', $viewer))
            ->assertRedirect();

        $this->actingAs($viewer)
            ->post(route('messages.start', $shared), ['body' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_only_participants_can_read_send_and_mark_a_conversation_read(): void
    {
        [$andrew, $maria, $outsider] = User::factory()->count(3)->create();
        $conversation = Conversation::between($andrew, $maria);
        $message = DirectMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $andrew->getKey(),
            'body' => 'Private text',
        ]);
        $conversation->update([
            'last_message_id' => $message->getKey(),
            'last_message_at' => $message->created_at,
            'user_one_last_read_message_id' => $message->getKey(),
        ]);

        $this->actingAs($outsider)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->post(route('messages.store', $conversation), ['body' => 'Intrusion'])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->post(route('messages.read', $conversation))
            ->assertForbidden();

        $this->actingAs($maria)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('active.messages.0.body', 'Private text')
                ->where('active.messages.0.isOwn', false)
                ->where('active.hasUnread', true)
                ->missing('active.messages.0.sender.email'));

        $this->actingAs($maria)
            ->post(route('messages.read', $conversation))
            ->assertRedirect();

        $this->assertSame(
            $message->getKey(),
            $conversation->fresh()->user_two_last_read_message_id,
        );
    }

    public function test_existing_history_remains_visible_after_a_block_but_new_messages_stop(): void
    {
        [$andrew, $maria] = User::factory()->count(2)->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $this->actingAs($andrew)
            ->post(route('messages.start', $maria), ['body' => 'Existing history']);

        $conversation = Conversation::query()->sole();

        $this->actingAs($maria)
            ->post(route('people.block', $andrew))
            ->assertRedirect();

        $this->actingAs($andrew)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('active.canSend', false)
                ->where('active.messages.0.body', 'Existing history'));

        $this->actingAs($andrew)
            ->post(route('messages.store', $conversation), ['body' => 'Should fail'])
            ->assertForbidden();
    }

    public function test_inbox_is_private_and_unread_state_is_owner_scoped(): void
    {
        [$andrew, $maria, $nikos] = User::factory()->count(3)->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);

        $this->actingAs($maria)
            ->post(route('messages.start', $andrew), ['body' => 'Unread for Andrew']);
        $this->actingAs($nikos)
            ->post(route('messages.start', $maria), ['body' => 'Not Andrew’s message']);

        $this->actingAs($andrew)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('conversations', 1)
                ->where('conversations.0.other.name', $maria->name)
                ->where('conversations.0.unread', true)
                ->where('messageSummary.unreadCount', 1)
                ->missing('conversations.0.other.email'));
    }

    public function test_message_body_is_trimmed_bounded_and_rate_limited(): void
    {
        [$andrew, $maria] = User::factory()->count(2)->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        RateLimiter::clear((string) $andrew->getKey());

        $this->actingAs($andrew)
            ->post(route('messages.start', $maria), ['body' => '  Hello  '])
            ->assertRedirect();

        $conversation = Conversation::query()->sole();
        $this->assertDatabaseHas('direct_messages', ['body' => 'Hello']);

        $this->actingAs($andrew)
            ->post(route('messages.store', $conversation), ['body' => str_repeat('x', 2001)])
            ->assertSessionHasErrors('body');

        for ($attempt = 0; $attempt < 28; $attempt++) {
            $this->actingAs($andrew)
                ->post(route('messages.store', $conversation), ['body' => "Message {$attempt}"])
                ->assertRedirect();
        }

        $this->actingAs($andrew)
            ->post(route('messages.store', $conversation), ['body' => 'Too many'])
            ->assertTooManyRequests();
    }
}
