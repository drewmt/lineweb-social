# Post draft contract

Lineweb Social gives members a private place to shape a post before it enters a
community. Drafts reuse the post and media foundations, but publication remains
an explicit state change with stricter visibility and side-effect rules.

## Member contract

- A verified active member may keep up to 50 unfinished posts.
- Draft text is required and follows the same 2,000-character limit as a
  published post.
- A draft belongs to a Space, and the author may move it to another Space where
  they can currently post.
- A draft may keep one normalized private WebP image. Alternative text is
  required while an image is attached.
- The author may resume, update, replace or remove the image, publish, or delete
  the draft.
- Publication keeps the same post identifier and permanent URL.

The limit is enforced inside a transaction while the author row is locked. It
prevents unbounded private storage without adding a cleanup job that could
silently remove a member's work.

## Privacy and authorization

An unpublished post is visible only to its author. Space owners, moderators,
other members, guests, APIs, feeds, search, profiles, topics, and notification
destinations cannot retrieve it. This is a deliberate stronger boundary than
moderation access to published content.

Every draft mutation rechecks both:

1. author ownership of the still-unpublished post; and
2. current permission to publish in the selected Space.

The browser-provided Space identifier is never trusted as authorization. If an
author loses access to the original Space, the editor offers only Spaces where
they can currently post.

## Publication boundary

Saving a draft does not:

- index hashtags;
- resolve or notify mentions;
- dispatch `PostPublished`; or
- expose the post or its image through public or authenticated feed resources.

Publishing runs under a post lock, revalidates the author and Space, applies the
final text and image state, assigns `published_at`, synchronizes topics, commits
the transaction, and then dispatches `PostPublished` once. Consumers should
listen for publication rather than treating draft creation as public content.

## Media lifecycle

Draft uploads use the same detection, dimension, decoding, metadata removal,
private storage, and WebP normalization rules as published images. Replacement
stores the new object before the old object is removed. A failed transaction
removes the uncommitted object; the previous image remains available to the
author. Deleting a draft removes its stored image through the post lifecycle.

## Data rights and extension guidance

Drafts are included in the author's personal data export as posts with a null
`published_at` value and safe media metadata. Account deletion removes them and
their private files.

Extensions must not query unpublished posts directly, infer draft counts for
other members, or emit notifications from draft writes. Future native draft
endpoints should preserve this contract and use separately scoped write
abilities rather than broadening the read-only feed API.
