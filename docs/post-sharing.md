# Quote posts and reposts

Lineweb Social treats a share as a new authored post that safely refers to one
original post. A member can leave the note empty for a repost or add context for
a quote post.

## Visibility and ownership

- A share is created only in the original post's Space. It cannot copy a
  private or shared-Space conversation into another community.
- The member must be able to view the original post and currently be allowed to
  publish in that Space. Blocked, hidden, draft, deleted, and already-shared
  posts cannot be shared.
- Each member has one share state per original post. Reposting again updates
  that member's existing share instead of producing duplicate feed items; an
  optional note turns it into a quote post.
- A share is ordinary authored content after creation: its author controls its
  note, it follows the same reactions, comments, reports, and moderation rules,
  and it never exposes a second-level share chain.

## Access changes and deletion

Every read rechecks the original post. If it becomes hidden, blocked, or
otherwise inaccessible, its body, author, media, and link are omitted from the
share projection. A quote can still show the sharing member's own words; an
empty repost disappears from feeds and direct access.

Deleting the original sets the reference to `NULL`. This preserves a quote
author's independent writing without retaining a copy of the original, while an
empty repost is no longer readable. The database constraint and read policy make
this behavior safe during normal delete races as well.

## API projection

`GET /api/v1/feed` and `GET /api/v1/posts/{post}` add a nullable `share`
object. It contains a minimal source projection only while the caller can still
view that source. `viewer.can_share` is `true` only for an eligible original
post; it is always `false` for a share, preventing recursive reshares.
