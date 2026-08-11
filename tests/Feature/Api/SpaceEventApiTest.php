<?php

namespace Tests\Feature\Api;

use App\Enums\SpaceEventRsvpStatus;
use App\Models\Space;
use App\Models\SpaceEvent;
use App\Models\SpaceEventRsvp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaceEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_spaces_read_token_lists_visible_events_with_aggregate_only_rsvps(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $attendee = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->private()->create();
        $space->addMember($viewer);
        $space->addMember($attendee);
        $event = SpaceEvent::factory()->for($space)->create([
            'created_by' => $owner->getKey(),
            'title' => 'Private planning session',
            'capacity' => 20,
        ]);
        SpaceEventRsvp::query()->create([
            'space_event_id' => $event->getKey(),
            'user_id' => $attendee->getKey(),
            'status' => SpaceEventRsvpStatus::Going,
        ]);

        $token = $viewer->createToken('Events API test', ['spaces:read'], now()->addDays(30))->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.spaces.events.index', $space))
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $event->getKey())
            ->assertJsonPath('data.0.rsvp.going_count', 1)
            ->assertJsonPath('data.0.rsvp.viewer_status', null)
            ->assertJsonMissing(['attendees' => []])
            ->assertJsonMissing(['user_id' => $attendee->getKey()]);

        $this->withToken($token)
            ->getJson(route('api.v1.events.show', $event))
            ->assertOk()
            ->assertJsonPath('data.title', 'Private planning session')
            ->assertJsonPath('data.rsvp.is_full', false);
    }

    public function test_event_api_enforces_space_visibility_and_token_ability(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->hidden()->create();
        $event = SpaceEvent::factory()->for($space)->create(['created_by' => $owner->getKey()]);

        $wrongAbility = $outsider->createToken('Wrong ability', ['feed:read'], now()->addDays(30))->plainTextToken;
        $spacesAbility = $outsider->createToken('Spaces ability', ['spaces:read'], now()->addDays(30))->plainTextToken;

        $this->withToken($wrongAbility)
            ->getJson(route('api.v1.events.show', $event))
            ->assertForbidden();
        $this->withToken($spacesAbility)
            ->getJson(route('api.v1.events.show', $event))
            ->assertForbidden();
    }

    public function test_event_collection_uses_a_viewer_space_and_scope_bound_cursor(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $otherViewer = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->private()->create();
        $space->addMember($viewer);
        $space->addMember($otherViewer);

        $past = SpaceEvent::factory()->for($space)->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $upcoming = collect([1, 2, 3])->map(fn (int $day): SpaceEvent => SpaceEvent::factory()
            ->for($space)
            ->create([
                'starts_at' => now()->addDays($day)->startOfHour(),
                'ends_at' => now()->addDays($day)->startOfHour()->addHour(),
            ]));

        $token = $viewer->createToken('Events cursor test', ['spaces:read'], now()->addDays(30))->plainTextToken;
        $otherToken = $otherViewer->createToken('Other events cursor test', ['spaces:read'], now()->addDays(30))->plainTextToken;

        $pageOne = $this->withToken($token)
            ->getJson(route('api.v1.spaces.events.index', [
                'space' => $space,
                'limit' => 2,
            ]));

        $pageOne
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', (string) $upcoming[0]->getKey())
            ->assertJsonPath('data.1.id', (string) $upcoming[1]->getKey())
            ->assertJsonPath('meta.scope', 'upcoming')
            ->assertJsonPath('meta.has_more', true);

        $cursor = $pageOne->json('meta.next_cursor');
        $this->assertIsString($cursor);

        $this->withToken($token)
            ->getJson(route('api.v1.spaces.events.index', [
                'space' => $space,
                'limit' => 2,
                'cursor' => $cursor,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $upcoming[2]->getKey())
            ->assertJsonPath('meta.has_more', false);

        $this->withToken($token)
            ->getJson(route('api.v1.spaces.events.index', [
                'space' => $space,
                'scope' => 'past',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $past->getKey())
            ->assertJsonPath('meta.scope', 'past');

        $invalidScopeCursor = $this->withToken($token)
            ->getJson(route('api.v1.spaces.events.index', [
                'space' => $space,
                'scope' => 'past',
                'cursor' => $cursor,
            ]));
        $invalidScopeCursor->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        $this->app['auth']->forgetGuards();
        $invalidViewerCursor = $this->withToken($otherToken)
            ->getJson(route('api.v1.spaces.events.index', [
                'space' => $space,
                'cursor' => $cursor,
            ]));
        $invalidViewerCursor->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->getJson(route('api.v1.spaces.events.index', [
                'space' => $space,
                'cursor' => 'x'.substr($cursor, 1),
            ]))
            ->assertBadRequest()
            ->assertJsonPath('code', 'invalid_cursor');

        $this->withToken($token)
            ->getJson(route('api.v1.spaces.events.index', [
                'space' => $space,
                'scope' => 'future-ish',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed');
    }
}
