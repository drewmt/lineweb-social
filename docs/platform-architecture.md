# Platform architecture

The project is a community engine, not a fixed social-network clone. The core should make common social products safe and maintainable while allowing adopters to replace presentation and add domain-specific capabilities.

## Core-owned contracts

The core owns invariants that an extension must not bypass:

- account identity, verification, authentication, and recovery;
- profile visibility, discovery, mute, and mutual block boundaries;
- Space visibility, roles, membership, invitations, and ownership;
- chronological posts and comments;
- post-image ownership, bounded normalization, private storage, parent-policy
  delivery, accessible projections, and lifecycle cleanup;
- report eligibility, moderation decisions, and append-only audit records;
- in-app notification ownership, member preferences, read state, and safe
  projections for core events;
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

## Near-term contract work

1. Implement the read-only resources in the contract-first
   [`api-v1.md`](api-v1.md) and [`openapi.json`](openapi.json) draft, preserving
   the stable web conversation, notification, and media policy boundaries.
2. Define queued email and push delivery contracts without making the current web UI or database writes depend on an external transport.
3. Implement and test the extension lifecycle before calling the manifest a plugin system.
4. Define quotas and asynchronous processing before expanding post media to
   galleries, video, direct uploads, or CDN delivery.

The goal is composability with secure defaults, not unlimited runtime code execution.
