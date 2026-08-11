# Space events

Space events provide an official, privacy-aware gathering layer for each
community. They are part of the core because their visibility, roles, attendance
privacy, capacity, cancellation, and audit rules must remain consistent with the
rest of the platform.

## Publishing and cancellation

- Only the Space owner or a moderator can publish or cancel an event.
- An event belongs to exactly one Space and inherits that Space's visibility.
- Title, description, venue, online URL, capacity, and schedule are bounded and
  server validated.
- A venue, an HTTPS online URL, or both are required. The platform stores the
  URL but never fetches it server-side.
- Browser-local times are accepted only with a valid IANA timezone, converted
  to UTC for storage, and round-tripped to reject invalid daylight-saving times.
- Events must begin in the future and cannot run longer than seven days.
- Cancellation is idempotent and preserves the event plus its audit history;
  it does not masquerade as deletion.

## RSVP privacy and capacity

Members can select `going` or `interested`, change that choice, or clear it.
Attendance records are private: web and API projections expose only aggregate
counts and the current viewer's own status. There is no attendee directory.

The `going` capacity check, RSVP change, and aggregate result are serialized in
one database transaction. Concurrent requests therefore cannot intentionally
overbook the final place. Interested responses do not consume capacity.

New responses close when the event starts or is cancelled. A member may still
remove their existing RSVP afterward so the platform never traps their private
attendance preference.

## API boundary

Bearer tokens with `spaces:read` can access:

- `GET /api/v1/spaces/{slug}/events`
- `GET /api/v1/events/{event}`

The collection defaults to upcoming events and accepts `scope=upcoming|past`,
an opaque cursor, and a `limit` from 1 to 50. It returns event details,
aggregate RSVP totals, current-viewer state, and a minimal Space reference. It
never returns creator, attendee, cancellation-actor, or raw membership records.

Event and RSVP writes intentionally remain on the first-party session web
surface for this slice. A future write API needs separate abilities, endpoint
rate limits, and equivalent transaction tests before it can be advertised.

## Extension events

The core dispatches domain events only after successful writes:

- `SpaceEventPublished`
- `SpaceEventCancelled`
- `SpaceEventRsvpChanged`

Listeners may build reminders, calendar exports, analytics, or integrations,
but must recheck current Space visibility before exposing event data. The core
does not currently promise recurring events, ticket sales, attendance lists,
calendar synchronization, reminders, or external conferencing integrations.
