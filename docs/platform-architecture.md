# Platform architecture

The project is a community engine, not a fixed social-network clone. The core should make common social products safe and maintainable while allowing adopters to replace presentation and add domain-specific capabilities.

## Core-owned contracts

The core owns invariants that an extension must not bypass:

- account identity, verification, authentication, and recovery;
- platform administrator assignment, account suspension state, privileged
  authorization, and append-only operator audit records;
- profile visibility, discovery, idempotent follows, mute, and mutual block
  boundaries;
- Space visibility, roles, membership, invitations, and ownership;
- bounded Space highlights with moderator-only writes, chronological timeline
  isolation, and append-only audit records;
- chronological posts and comments;
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
search indexes, analytics, commerce, learning, events, or alternative feed
presentation. Extension data belongs in extension-owned tables and must reference
core entities with explicit foreign keys.

The current manifest is a deploy-time declaration prototype. Its permission and UI-slot allowlists document intent, but they are not yet a runtime sandbox or a supported marketplace API. No extension should be advertised as one-click installable until provider bootstrapping, migrations, compatibility checks, asset loading, failure isolation, and uninstall behavior are implemented and tested.

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

The first post-image projection exposes only an authorized application URL,
alternative text, and normalized dimensions. Storage disks, object paths,
checksums, and source metadata remain server-side. Feed, permalink, and profile
views consume this shared projection; the delivery controller rechecks the parent
post policy on every request. The full contract is documented in
[`media.md`](media.md).

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
moderation. Administrator assignment is console-only, every web entry point is
protected by an explicit role middleware, and the write service rechecks the
actor under a database lock. Suspension revokes active sessions and API tokens
but does not silently remove public content. Extensions must enforce the shared
active-account middleware on member-authenticated write and API surfaces. The
full contract is documented in
[`platform-administration.md`](platform-administration.md).

## Near-term contract work

1. Expand the authenticated read-only API beyond the available profile
   resources using the contract-first [`api-v1.md`](api-v1.md) and
   [`openapi.json`](openapi.json) draft, preserving the stable web conversation,
   notification, and media policy boundaries.
2. Define queued email and push delivery contracts without making the current web UI or database writes depend on an external transport.
3. Implement and test the extension lifecycle before calling the manifest a plugin system.
4. Define quotas and asynchronous processing before expanding post media to
   galleries, video, direct uploads, or CDN delivery.

The goal is composability with secure defaults, not unlimited runtime code execution.
