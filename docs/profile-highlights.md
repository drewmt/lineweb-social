# Profile highlights

Profile highlights let a member choose up to three of their own posts as a
short introduction to their work. They appear in a separate responsive rail on
the member profile and never change the chronological activity timeline.

## Core rules

- A member may pin only their own published, visible posts.
- Each post may be pinned once and each profile may hold at most three pins.
- Pin and unpin requests are idempotent and rate-limited.
- Hiding, unpublishing, or deleting a post removes its profile highlight.
- Stale highlights that no longer pass the author's current Space access are
  discarded when the member next pins a post.
- A profile viewer sees a highlight only when the underlying post, author, and
  Space remain visible to that viewer.

## Write boundary

`ManageProfileHighlights` locks the profile and target post in one transaction,
rechecks author ownership and post visibility, removes stale rows, and enforces
the three-item limit. A real change emits `ProfilePostHighlightChanged` after
the transaction. Idempotent requests do not emit duplicate events.

The dedicated `profile_post_highlights` table keeps profile curation separate
from post publication time. Foreign keys remove rows with their parent profile
or post.

## Projection boundary

The web profile resolves highlighted post identifiers through the same
`VisiblePostQuery` used by feeds, then presents them independently from recent
activity. On small screens the cards use an edge-to-edge swipe rail; wider
screens use a compact three-column collection.

The read-only profile API exposes only `post_id`, the canonical API post URL,
and `highlighted_at`. It reapplies current viewer visibility and never exposes
hidden selections. The member's personal data export includes their complete
profile-highlight references.
