# Community Stories

Stories are a small, core-owned publishing surface for timely Space updates.
They are intentionally narrower than permanent posts and do not introduce a
second social graph, a hidden ranking system, or a viewer analytics system.

## Current contract

- A verified, active Space member may publish text, one image, or both.
- Text is limited to 280 characters.
- A member may have at most five active Stories in one Space.
- Every Story expires exactly 24 hours after creation.
- The author may delete a Story before expiry. Space owners and moderators may
  remove member Stories.
- Stories have no replies, reactions, reposts, view counters, or viewer list.
- Video, audio, animation, external media URLs, and direct-to-cloud uploads are
  outside the current scope.

The active limit is enforced inside a transaction under a locked Space row.
Parallel requests cannot silently exceed the per-member, per-Space boundary.
Publishing is also rate limited to ten attempts per account each hour.

## Visibility and safety

A Story inherits its Space visibility. Public Space Stories are visible to
authenticated members who may view that Space. Private and hidden Space Stories
remain limited by the existing membership policy.

Every feed, viewer, and image request reapplies the current Space policy and
mutual block boundary. A copied Story or image URL does not grant access. An
expired Story returns no content even if the scheduled cleanup has not run yet.

The feature deliberately stores no viewer identity. Operators cannot recover a
viewer list because core never creates one.

Moderator removals create an append-only Space audit entry with the actor,
author, Story identifier, and timestamp. The audit trail never retains the
Story body, image, or storage metadata.

## Images and storage

Story images use the same untrusted-upload boundary as post images. JPEG, PNG,
and WebP uploads may be at most 8 MiB and are checked against the configured
decoded-pixel limit. The original upload is never retained.

Core removes source metadata, normalizes pixels to a static WebP image, limits
the longest edge to 2,048 pixels, uses an opaque generated path, and stores the
result on the configured private media disk. The browser receives only an
authorized application URL, member-authored alternative text, and normalized
dimensions. It never receives the storage disk, object path, checksum, source
filename, or size.

## Expiry, deletion, and export

`php artisan stories:prune` permanently removes expired Story records and their
private image objects. The scheduler runs this command hourly with overlap
protection. A normal read filters by `expires_at` first, so scheduler delay does
not extend public visibility.

Author deletion, account deletion, and Space deletion remove owned image files
through the same lifecycle. Personal data export includes safe metadata for
currently active Stories only. Expired Stories are not a retained archive.

Deployers must run Laravel's scheduler and include private media storage in
their backup and deletion procedures. Independent backups and infrastructure
logs remain subject to the deployer's own retention policy.

## Product boundary

Stories are implemented in core because visibility, block behavior, media
authorization, expiry, and account deletion are shared platform invariants. The
presentation may later become replaceable after the JavaScript UI-slot contract
is stable. Reels remain outside the core until the project defines video
transcoding, object quotas, delivery, captions, copyright handling, reporting,
and moderation operations.

The initial Stories slice is web-first. A versioned bearer API contract should
be added before a native client depends on it.
