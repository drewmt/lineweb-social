# Space highlights

Space highlights give community teams a small, deliberate starting point without
changing the chronological timeline. A Space owner or moderator can highlight
up to three published, visible posts. Members see those selections in a separate
responsive rail before the normal feed.

## Core rules

- Only current Space owners and moderators may add or remove highlights.
- A highlighted post must belong to the same Space, be published, and remain
  visible when it is added.
- Each post may be highlighted once and each Space may hold at most three.
- Adding or removing an existing state is idempotent.
- A moderator may remove a highlight after its post is hidden so stale
  moderation state can always be cleaned up.
- Highlight order follows the time each selection was made. It never changes
  post publication time or the chronological order of the Space timeline.

## Write and audit boundary

The highlight service locks the Space and target post inside one transaction,
rechecks authorization and post state, and enforces the three-item limit at the
database boundary. Successful changes create an append-only Space audit entry
and then emit `PostHighlightChanged`. No event or duplicate audit record is
created for an idempotent request.

The dedicated `space_post_highlights` table keeps curation metadata separate
from authored content. Deleting the parent post or Space cascades the record;
deleting the moderator retains the highlight but nulls its operator reference.

## Projection boundary

Web Space pages receive a bounded `highlights` projection produced by the same
visibility filters as the timeline. Normal feed posts expose only
`isHighlighted` and `highlightedAt` so shared cards can render state without
learning who selected it.

The read-only API adds a nullable `highlighted_at` timestamp to the post
resource. It deliberately excludes moderator identity and does not introduce an
API write endpoint in this version.
