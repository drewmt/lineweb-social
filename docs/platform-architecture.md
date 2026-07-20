# Platform architecture

The project is a community engine, not a fixed social-network clone. The core should make common social products safe and maintainable while allowing adopters to replace presentation and add domain-specific capabilities.

## Core-owned contracts

The core owns invariants that an extension must not bypass:

- account identity, verification, authentication, and recovery;
- profile visibility, discovery, mute, and mutual block boundaries;
- Space visibility, roles, membership, invitations, and ownership;
- chronological posts and comments;
- report eligibility, moderation decisions, and append-only audit records;
- rate limits, server validation, and policy authorization;
- domain events emitted only after successful writes.

An Instagram-like product may render media-first cards, an X-like product may render compact conversations, and a professional network may add organizations and jobs. They should still inherit the same visibility and safety rules.

## Extension-owned capabilities

Extensions may add new content projections, integrations, notifications, search indexes, analytics, commerce, learning, events, or alternative feed presentation. Extension data belongs in extension-owned tables and must reference core entities with explicit foreign keys.

The current manifest is a deploy-time declaration prototype. Its permission and UI-slot allowlists document intent, but they are not yet a runtime sandbox or a supported marketplace API. No extension should be advertised as one-click installable until provider bootstrapping, migrations, compatibility checks, asset loading, failure isolation, and uninstall behavior are implemented and tested.

## Presentation boundary

React pages consume server-produced view models rather than raw database models. New layouts should reuse those contracts or introduce versioned projections instead of querying around policies. Design tokens provide a controlled visual baseline; future themes should override semantic tokens and registered presentation components, not copy the application shell.

## Near-term contract work

1. Add stable, paginated conversation projections for web, mobile, and extension clients.
2. Add notification events and delivery preferences without coupling them to one UI.
3. Define media ownership, processing, privacy, and deletion before adding upload controls.
4. Add versioned API resources for native and decoupled clients.
5. Implement and test the extension lifecycle before calling the manifest a plugin system.

The goal is composability with secure defaults, not unlimited runtime code execution.
