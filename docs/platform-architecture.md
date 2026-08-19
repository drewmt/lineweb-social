# Platform architecture

The project is a community engine, not a fixed social-network clone. The core should make common social products safe and maintainable while allowing adopters to replace presentation and add domain-specific capabilities.

## Core-owned contracts

The core owns invariants that an extension must not bypass:

- account identity, verification, authentication, and recovery;
- platform administrator assignment, account suspension state, privileged
  authorization, and append-only operator audit records;
- profile visibility, discovery, idempotent follows, mute, and mutual block
  boundaries;
- author-owned Profile Highlights with a three-post bound and current-viewer
  post visibility;
- Space visibility, roles, membership, account-specific invitations, bounded
  shareable invitation links, and ownership;
- official Space events with owner/moderator creation, private member RSVP
  identities, aggregate attendance, capacity serialization, cancellation, and
  append-only audit records;
- bounded Space highlights with moderator-only writes, chronological timeline
  isolation, and append-only audit records;
- chronological posts and comments;
- fixed-lifetime Space Stories with bounded publishing, current visibility and
  block checks, normalized private images, no viewer identities, and permanent
  expiry cleanup;
- author-only unpublished posts, bounded draft ownership, and explicit
  publication side effects;
- normalized post topics whose pages, visible counts, and search results reuse
  current post-visibility boundaries;
- a separate chronological Following projection that reapplies current profile,
  Space, moderation, mute, and block visibility;
- one allowlisted post reaction per member, aggregate-only public projections,
  and after-transaction reaction change events;
- post-image ownership, bounded normalization, private storage, parent-policy
  delivery, accessible projections, and lifecycle cleanup;
- report eligibility, moderation decisions, and append-only audit records;
- in-app notification ownership, member preferences, read state, and safe
  projections for core events;
- direct-conversation participant scope, canonical member pairs, unread state,
  block-aware sending, and bounded message projections;
- rate limits, server validation, and policy authorization;
- domain events emitted only after successful writes.

An Instagram-like product may render media-first cards, an X-like product may render compact conversations, and a professional network may add organizations and jobs. They should still inherit the same visibility and safety rules.

## Extension-owned capabilities

Extensions may add new content projections, integrations, notification channels,
search indexes, analytics, commerce, learning, ticketing, or alternative feed
presentation. Extension data belongs in extension-owned tables and must reference
core entities with explicit foreign keys.

The current manifest and compatibility inspector form a deploy-time declaration
boundary. Permission and UI-slot allowlists document intent, while the
administrator Extension Center and `platform:extensions` command validate each
manifest independently. Reviewed providers may be enabled only from deploy
configuration; every selected provider is preflighted before registration, and
an unsafe activation plan fails application startup. This is not a runtime
sandbox or a supported marketplace API. Reviewed extension migrations now use
an extension-scoped ownership/checksum registry and explicit backup-gated
deploy/rollback commands; source drift or missing applied source blocks
activation. Data is retained when source is removed. Pre-built CSS and ES
modules use a separate bounded, immutable, SRI-backed publication lifecycle;
they remain trusted same-origin code, not sandboxed marketplace packages. No
extension should be advertised as one-click installable until a stable
JavaScript slot SDK and separately reviewed uninstall contract are implemented
and tested. The current contracts are documented in
[`extensions.md`](extensions.md), [`extension-migrations.md`](extension-migrations.md),
and [`extension-assets.md`](extension-assets.md).

## Presentation boundary

React pages consume server-produced view models rather than raw database models. New layouts should reuse those contracts or introduce versioned projections instead of querying around policies. Design tokens provide a controlled visual baseline; future themes should override semantic tokens and registered presentation components, not copy the application shell.

The web post permalink now consumes a dedicated server-side conversation
projection. It preserves Space visibility, publication, moderation, mute, block,
profile-visibility, and report-state boundaries while exposing comments in
chronological 20-item pages. Feed previews link into this canonical view instead
of attempting to load an unbounded thread inline.

The post composer writes unfinished content through a separate draft service.
Drafts reuse post identifiers and normalized private media, but remain
author-only and absent from feeds, search, profiles, topics, mentions, APIs, and
moderation queues. Publication revalidates current Space membership, commits the
final post state, and only then emits the normal publication event. The full
contract is documented in [`post-drafts.md`](post-drafts.md).

The notification center consumes a separate server-side projection over Laravel
database notifications. Stored payloads contain identifiers only. Every render
and open action resolves the current entity state, policy authorization, profile
visibility, and Space role before exposing a destination. This lets a stale
notification become unavailable without leaking deleted or newly restricted data.
The opt-in daily email digest reuses that projection at queue-processing time but
exports only category counts. A timestamp-and-notification cursor bounds each
job without dropping a high-volume backlog, while scheduler dispatch and mail
transport remain outside content-write transactions. The full contract is
documented in [`notifications.md`](notifications.md).

The first post-image projection exposes only an authorized application URL,
alternative text, and normalized dimensions. Storage disks, object paths,
checksums, and source metadata remain server-side. Feed, permalink, and profile
views consume this shared projection; the delivery controller rechecks the parent
post policy on every request. The full contract is documented in
[`media.md`](media.md).

Community Stories reuse the core Space, block, media, and account-deletion
boundaries while adding a fixed 24-hour lifecycle. Expiry is enforced on reads
before the hourly pruning command removes the row and private object. Core does
not store viewer identities, reactions, or replies for this surface. The
initial version is web-first and must gain a versioned bearer API projection
before native clients depend on it. The full contract is documented in
[`stories.md`](stories.md).

Typed post reactions follow the same boundary. The core stores one allowlisted
reaction per member and post, serializes changes with the parent post, and emits
`PostReactionChanged` only after a successful write. Feed, permalink, and API
projections expose aggregate counts plus the current viewer's type; they never
expose reactor identities. Extensions may listen to the event for analytics or
batched notifications, but must recheck post visibility before delivery.

Space highlights are a bounded curation layer rather than a ranking signal.
Owners and moderators can select at most three published, visible posts under a
serialized Space lock. The highlight projection reuses normal post visibility,
does not reorder the timeline, and exposes no selecting-member identity to web
or API readers. The full contract is documented in
[`space-highlights.md`](space-highlights.md).

Profile highlights are member-owned presentation, not feed ranking. A member
may select at most three of their own published posts under a serialized
profile lock. The profile projection resolves those identifiers through normal
post visibility for every viewer, while recent activity remains chronological.
Hidden, unpublished, deleted, or no-longer-accessible posts cannot occupy a new
selection slot indefinitely. The full contract is documented in
[`profile-highlights.md`](profile-highlights.md).

Space events are official community records, not ordinary feed posts. Only an
owner or moderator may publish or cancel one; members can change their own RSVP
until the event starts, while cancellation and capacity checks are serialized
server-side. Web and API projections expose aggregate attendance and the
current viewer's status only. The full contract is documented in
[`space-events.md`](space-events.md).

Shareable Space invitations are member-onboarding credentials, not public role
grants. Owners and moderators can create them, but every acceptance assigns the
regular member role. Plaintext tokens are displayed once and only SHA-256
digests are persisted. Expiry, usage limits, revocation, active-link quotas,
row locking, active-account checks, email verification, and append-only Space
audit records remain core invariants. The full boundary is documented in
[`space-invite-links.md`](space-invite-links.md).

Post topics are normalized indexes, not independent public content. Topic pages
and search counts begin from the same policy-filtered post query, so a tag never
grants access to a private Space or muted, blocked, draft, or moderated content.
The full contract is documented in [`topics.md`](topics.md).

Follow relationships are distinct from safety relationships. Blocking removes
follows in both directions inside the same serialized transaction, while muting
keeps the relationship but removes that member's content from the viewer's
feeds. `UserFollowChanged` is emitted only after a real committed change.
Profiles expose aggregate counts and current-viewer state, never follower lists
in this initial contract.

Direct messages use one canonical conversation row per ordered member pair.
Only the two participants may retrieve its inbox projection, bounded recent
history, or unread state. Starting a thread reuses profile visibility and mutual
block rules; every later send rechecks blocks inside the locked conversation
transaction. A later block stops delivery but does not silently erase existing
history or safety evidence. Participants can report only an incoming message
from their own conversation. The platform queue exposes the exact submitted
message snapshot rather than surrounding history; decisions are separately
audited and closed evidence is scheduled for bounded retention. The current web
slice deliberately excludes
attachments, groups, realtime presence, delivery receipts, and end-to-end
encryption claims. The full boundary is documented in
[`direct-messages.md`](direct-messages.md) and
[`message-reporting.md`](message-reporting.md).

Platform administration is a separate privileged boundary from Space
moderation. Its dedicated Overview, Members, Appeals, Safety, and Audit surfaces
share one protected operator shell, while every web entry point remains
protected by explicit role middleware. Administrator assignment is console-only,
and write services recheck the actor under a database lock. Suspension revokes
active sessions and API tokens but does not silently remove public content.
Each restriction has one bounded, human-reviewed member appeal; approval is an
explicit transactional account action rather than automated enforcement.
Extensions must enforce the shared active-account middleware on
member-authenticated write and API surfaces. The full contracts are documented
in [`platform-administration.md`](platform-administration.md) and
[`account-appeals.md`](account-appeals.md).

## Near-term contract work

1. Expand the authenticated read-only API beyond the available social and event
   resources using the contract-first [`api-v1.md`](api-v1.md) and
   [`openapi.json`](openapi.json) draft, preserving the stable web conversation,
   notification, and media policy boundaries.
2. Define a separately opt-in push delivery contract without making the current
   web UI or database writes depend on an external transport.
3. Design and test a stable JavaScript UI-slot SDK plus a separately reviewed
   destructive uninstall contract before calling the manifest a complete
   marketplace plugin system.
4. Define quotas and asynchronous processing before expanding post media to
   galleries, video, direct uploads, or CDN delivery.

The goal is composability with secure defaults, not unlimited runtime code execution.
