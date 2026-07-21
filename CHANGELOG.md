# Changelog

All notable project changes will be documented here.

## Unreleased

### Added

- Optional single-image post attachments with required alternative text,
  private policy-protected delivery, bounded processing, and static WebP
  normalization that discards original metadata and filenames.
- Media lifecycle cleanup when a parent post, Space, or account is deleted,
  plus a public contract for storage, authorization, and future extensions.
- Permanent post links with a responsive full-conversation view, chronological
  20-comment pages, and policy-filtered access to older replies.
- Database-backed in-app notifications for replies and new Space moderation
  reports, with unread state, secure destinations, and paginated history.
- Per-member preferences for reply and moderation notification categories.

### Changed

- Dependabot now groups only compatible minor and patch dependency updates;
  major upgrades remain isolated for focused review.
- Mobile Space pulse cards now separate cover imagery from their text content,
  with consistent spacing for titles and member counts.
- The feed composer now brings identity, Space selection, writing, and publishing
  into one clear surface with accessible mobile touch targets.
- Mobile Space cards now begin on the shared 16px content gutter while their
  horizontal scroll rail remains edge-to-edge.
- README product previews now show the current desktop feed, mobile feed, and
  member-profile interfaces.

## [0.1.0-alpha.1] - 2026-07-20

### Added

- Laravel 13, React 19, Inertia 3, TypeScript, and Tailwind CSS 4 application foundation.
- Verified-account access with passkeys and two-factor authentication support.
- Public, private, and hidden spaces with owner, moderator, and member roles.
- Membership-protected publishing and a chronological community feed.
- Membership-protected comments with bounded input, dedicated throttling,
  extension events, and a compact responsive conversation surface.
- Space directory with client-side discovery search and joined-space filtering.
- Rate-limited Space creation with collision-safe slugs and automatic owner membership.
- Policy-protected public join and member leave flows; owners cannot abandon their Space.
- Seven-day Space invitations with normalized recipient emails, hashed tokens, verified-account matching, cancellation, and role-aware permissions.
- Owner-only moderator role changes and atomic ownership transfer that keeps the previous owner as a moderator.
- Reason-required member removal with moderator boundaries and append-only Space audit records.
- Responsive Space management and invitation acceptance screens.
- Publishing validation, authorization policies, and per-user rate limiting.
- Bright, social-first responsive interface with light mode as the default.
- App-first responsive shell with a fixed desktop rail, compact mobile header,
  and native-style bottom navigation.
- Modern chronological feed with a focused composer, horizontal Space pulse,
  identity-aware avatars, and a contextual desktop community rail.
- Visual Space discovery with compact editorial hierarchy, responsive
  search/filter controls, and redesigned creation and management surfaces.
- Optimized photographic default covers for Space identity, a separate human
  People image, typographic profile headers, and a content-led feed context rail.
- Stable member handles and editable profiles with a concise headline,
  validated bio, location, website, and identity fields.
- Privacy-aware People discovery with public, shared-Space-only, and private
  profile visibility plus a separate discovery opt-out.
- Policy-protected profile pages that expose only Spaces and posts the current
  viewer is already allowed to see.
- One-way private muting and mutual blocking with server-enforced profile,
  discovery, and feed boundaries.
- Dedicated Safety settings for reviewing and reversing muted or blocked
  relationships.
- Refined social surfaces and controls with calmer card elevation, tactile
  button states, consistent touch targets, and a community-native visual marker.
- Editorial public homepage with an honest product story, distinct visual
  sections, responsive product previews, and no fabricated social metrics.
- Reworked desktop and mobile navigation with clearer hierarchy, five-way
  mobile access, a dedicated publishing action, and a first-class profile path.
- Complete privacy-aware profile presentation with a branded identity header,
  About details, real visible-content totals, Space context, and an activity
  timeline. Settings and People discovery now share the same visual hierarchy.
- Private post and comment reporting with shared enum-backed workflow rules,
  duplicate protection, visibility-aware policy checks, and dedicated throttling.
- A unified Space-scoped moderator queue with review, hide, dismiss, and reopen
  actions; hidden content leaves community surfaces until all removal decisions are reopened.
- Append-only moderation audit entries, after-transaction report events, and
  public extension guidance for Laravel listeners and new reportable types.
- Local extension-manifest validation with permission and UI-slot allowlists.
- Public platform-architecture guidance separating core safety invariants from
  product-specific presentation and extension-owned capabilities.
- Feature, authorization, manifest, lint, type, and build checks.

[Unreleased]: https://github.com/drewmt/lineweb-social/compare/v0.1.0-alpha.1...HEAD
[0.1.0-alpha.1]: https://github.com/drewmt/lineweb-social/releases/tag/v0.1.0-alpha.1
