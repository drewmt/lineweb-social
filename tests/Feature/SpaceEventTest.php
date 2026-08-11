<?php

namespace Tests\Feature;

use App\Enums\SpaceAuditAction;
use App\Enums\SpaceEventRsvpStatus;
use App\Enums\SpaceRole;
use App\Events\SpaceEventCancelled;
use App\Events\SpaceEventPublished;
use App\Events\SpaceEventRsvpChanged;
use App\Models\Space;
use App\Models\SpaceEvent;
use App\Models\SpaceEventRsvp;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpaceEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_moderator_can_publish_an_event_but_regular_members_cannot(): void
    {
        CarbonImmutable::setTestNow('2026-08-11 09:00:00 UTC');
        Event::fake([SpaceEventPublished::class]);
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create(['slug' => 'makers']);
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);

        $payload = [
            'title' => '  Open source night  ',
            'description' => '  A practical evening for builders.  ',
            'starts_at' => '2026-08-20T18:30',
            'ends_at' => '2026-08-20T21:00',
            'timezone' => 'Europe/Athens',
            'venue' => '  Community Lab  ',
            'online_url' => 'https://events.example.com/room',
            'capacity' => 80,
        ];

        $this->actingAs($moderator)
            ->post(route('spaces.events.store', $space), $payload)
            ->assertRedirect();

        $event = SpaceEvent::query()->firstOrFail();
        $this->assertSame('Open source night', $event->title);
        $this->assertSame('A practical evening for builders.', $event->description);
        $this->assertSame('Community Lab', $event->venue);
        $this->assertTrue($event->starts_at->equalTo(CarbonImmutable::parse('2026-08-20 15:30:00 UTC')));
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'action' => SpaceAuditAction::EventCreated->value,
        ]);
        Event::assertDispatched(SpaceEventPublished::class, fn (SpaceEventPublished $published): bool => $published->event->is($event));

        $this->actingAs($member)
            ->post(route('spaces.events.store', $space), [
                ...$payload,
                'title' => 'Member event',
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('spaces.events.store', $space), [
                ...$payload,
                'title' => 'Owner event',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('space_events', 2);
    }

    public function test_event_validation_rejects_unsafe_links_invalid_windows_and_missing_location(): void
    {
        CarbonImmutable::setTestNow('2026-08-11 09:00:00 UTC');
        $owner = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->from(route('spaces.events.index', $space))
            ->post(route('spaces.events.store', $space), [
                'title' => 'Unsafe event',
                'starts_at' => '2026-08-11T12:00',
                'ends_at' => '2026-08-19T12:01',
                'timezone' => 'Europe/Athens',
                'online_url' => 'http://example.com/join',
                'capacity' => 1,
            ])
            ->assertRedirect(route('spaces.events.index', $space))
            ->assertSessionHasErrors(['ends_at', 'online_url', 'capacity']);

        $this->actingAs($owner)
            ->post(route('spaces.events.store', $space), [
                'title' => 'Nowhere event',
                'starts_at' => '2026-08-20T12:00',
                'ends_at' => '2026-08-20T13:00',
                'timezone' => 'Europe/Athens',
            ])
            ->assertSessionHasErrors('venue');

        $this->actingAs($owner)
            ->post(route('spaces.events.store', $space), [
                'title' => 'Nonexistent daylight time',
                'starts_at' => '2027-03-28T03:30',
                'ends_at' => '2027-03-28T05:00',
                'timezone' => 'Europe/Athens',
                'venue' => 'Community Lab',
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseEmpty('space_events');
    }

    public function test_event_surfaces_follow_current_space_visibility_and_never_expose_attendee_identities(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->private()->create();
        $space->addMember($member);
        $event = SpaceEvent::factory()->for($space)->create(['created_by' => $owner->getKey()]);
        SpaceEventRsvp::query()->create([
            'space_event_id' => $event->getKey(),
            'user_id' => $owner->getKey(),
            'status' => SpaceEventRsvpStatus::Going,
        ]);

        $this->actingAs($member)
            ->get(route('spaces.show', $space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.0.id', $event->getKey())
                ->where('events.0.goingCount', 1)
                ->missing('events.0.rsvps')
                ->missing('events.0.attendees'));

        $this->actingAs($member)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.id', $event->getKey())
                ->where('event.goingCount', 1)
                ->where('event.viewerStatus', null));

        $this->actingAs($outsider)
            ->get(route('spaces.events.index', $space))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('events.show', $event))
            ->assertForbidden();
    }

    public function test_rsvps_are_idempotent_changeable_and_capacity_safe(): void
    {
        Event::fake([SpaceEventRsvpChanged::class]);
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($first);
        $space->addMember($second);
        $event = SpaceEvent::factory()->for($space)->create([
            'created_by' => $owner->getKey(),
            'capacity' => 1,
        ]);

        $this->actingAs($first)
            ->put(route('events.rsvps.store', $event), ['status' => 'going'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Your RSVP is updated.');
        $this->actingAs($first)
            ->put(route('events.rsvps.store', $event), ['status' => 'going'])
            ->assertSessionHas('status', 'Your RSVP is already up to date.');

        $this->actingAs($second)
            ->put(route('events.rsvps.store', $event), ['status' => 'going'])
            ->assertSessionHasErrors('status');
        $this->actingAs($second)
            ->put(route('events.rsvps.store', $event), ['status' => 'interested'])
            ->assertRedirect();

        $this->actingAs($first)
            ->put(route('events.rsvps.store', $event), ['status' => 'interested'])
            ->assertRedirect();
        $this->actingAs($second)
            ->put(route('events.rsvps.store', $event), ['status' => 'going'])
            ->assertRedirect();

        $this->assertDatabaseCount('space_event_rsvps', 2);
        $this->assertDatabaseHas('space_event_rsvps', [
            'space_event_id' => $event->getKey(),
            'user_id' => $second->getKey(),
            'status' => SpaceEventRsvpStatus::Going->value,
        ]);
        Event::assertDispatchedTimes(SpaceEventRsvpChanged::class, 4);
    }

    public function test_cancellation_is_audited_idempotent_and_stops_new_rsvps_while_allowing_removal(): void
    {
        Event::fake([SpaceEventCancelled::class]);
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);
        $event = SpaceEvent::factory()->for($space)->create(['created_by' => $owner->getKey()]);
        SpaceEventRsvp::query()->create([
            'space_event_id' => $event->getKey(),
            'user_id' => $member->getKey(),
            'status' => SpaceEventRsvpStatus::Going,
        ]);

        $this->actingAs($member)
            ->patch(route('events.cancel', $event))
            ->assertForbidden();
        $this->actingAs($moderator)
            ->patch(route('events.cancel', $event))
            ->assertRedirect()
            ->assertSessionHas('status', 'Event cancelled.');
        $this->actingAs($moderator)
            ->patch(route('events.cancel', $event))
            ->assertSessionHas('status', 'Event was already cancelled.');

        $this->actingAs($member)
            ->put(route('events.rsvps.store', $event), ['status' => 'interested'])
            ->assertForbidden();
        $this->actingAs($member)
            ->delete(route('events.rsvps.destroy', $event))
            ->assertRedirect()
            ->assertSessionHas('status', 'Your RSVP was removed.');

        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'action' => SpaceAuditAction::EventCancelled->value,
        ]);
        $this->assertDatabaseEmpty('space_event_rsvps');
        Event::assertDispatchedTimes(SpaceEventCancelled::class, 1);
    }
}
