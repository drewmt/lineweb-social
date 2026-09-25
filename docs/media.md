# Private post media contract

Lineweb Social treats uploaded media as untrusted content. Core posts and drafts
may contain a bounded gallery of static images, while storage, authorization,
accessibility, and deletion remain server-enforced platform contracts.

## Current scope

- Post text remains required.
- A post may contain up to four JPEG, PNG, or WebP uploads.
- Each upload may be at most 8 MiB and the combined raw gallery upload may be at
  most 20 MiB.
- Alternative text is required for every image and is limited to 300
  characters per item.
- Each decoded source may contain at most 12 million pixels.
- Every stored result is a static WebP image, at most 2,048 pixels on its
  longest edge.
- Draft authors may retain or remove existing items, edit their alternative
  text, and append new items. Retained order is stable and new items append.
- A published gallery is immutable in this release. Drag reordering, audio,
  animation, remote URL imports, direct-to-cloud uploads, and public CDN URLs
  remain outside the contract. Short video is a separate opt-in post format.

The legacy single `image` and `image_alt` request fields remain accepted during
the legacy gallery transition. A legacy draft upload replaces the previous primary
image. New clients use `images[]` and `image_alts[]`; requests cannot mix the
two upload contracts.

## Upload trust boundary

File extensions and browser-provided content types are not trusted. The server
checks detected MIME type and dimensions before full decoding, enforces byte
and pixel bounds, and decodes each item with GD.

Original uploads are never stored. Pixels are orientation-corrected, resized
when necessary, and re-encoded as WebP under generated names. This removes
original filenames, EXIF/GPS metadata, embedded profiles, animation, and data
appended to otherwise valid images. All items are normalized before the
database transaction begins. A decoder, encoder, storage, or database failure
rejects the write and cleans every new object instead of leaving a partial
gallery.

## Ordering, ownership, and authorization

Each media row belongs to exactly one post and has a unique zero-based position
within that post. The first item is the primary image for backward-compatible
web and API projections. Additive `mediaItems` and `media_items` projections
expose the complete ordered gallery.

Every item inherits its post's author, Space, visibility, mute/block,
publication, and moderation rules. Public projections expose only an opaque
item identifier, authorized application URL, alternative text, normalized
dimensions, and, on the API, MIME type. Storage disks, paths, checksums, sizes,
and original filenames are never public.

Unpublished media is author-only. Space owners and moderators do not gain
access to another member's draft. Every binary request re-authorizes the parent
post and scopes the requested media identifier through that post, preventing a
valid item identifier from being reused across posts.

Files live on the configured private media disk and are not symlinked into the
web root. Authorized controllers verify that the object still exists and serve
only normalized WebP responses with private caching, `nosniff`, and a restrictive
Cross-Origin-Resource-Policy header.

Hiding a post immediately removes ordinary-member access to every item while
retaining the existing author/moderator policy. Deleting a post, Space, account,
or draft removes all owned objects and rows.

## Draft mutation safety

A draft update explicitly names retained media and its new alternative text.
Those identifiers must belong to the locked draft. Removed rows are deleted in
the same database transaction; their files are removed only after commit.
Newly normalized objects are tracked and cleaned if the transaction fails.
This ordering prevents a failed edit from destroying the last saved gallery.

## Accessibility and presentation

Alternative text is member-authored; core does not invent descriptions from
filenames or AI. Width and height are persisted so clients can reserve stable
layout space. Feed, permalink, and profile views use a native horizontal
scroll-snap gallery with touch swiping, keyboard-operable controls, position
feedback, and the same ordered source contract.

## Story image boundary

Community Stories accept at most one JPEG, PNG, or WebP image up to 8 MiB. They
reuse the same decoder, pixel limit, metadata removal, WebP normalization,
private storage, opaque path, and authorized delivery rules as post images.

A Story image is re-authorized against the current Space and mutual block
policies on every request. It is unavailable immediately after expiry, even
before scheduled cleanup. Author deletion, account deletion, Space deletion,
and hourly pruning permanently remove the stored object. Core stores no viewer
identity and does not issue public or long-lived media URLs. The complete
lifecycle is documented in [`stories.md`](stories.md).

## Abuse and operational limits

Publishing rate limits also bound image-processing work. Input bytes, combined
request bytes, decoded pixels, output dimensions, and items per post are
limited independently. Deployments need PHP GD with WebP support plus EXIF and
Fileinfo. PHP `upload_max_filesize` must allow 8 MiB per file and
`post_max_size` must leave room for the 20 MiB gallery plus multipart overhead;
24 MiB or more is a practical baseline.

Operators may point `MEDIA_DISK` at another configured private Laravel disk
for images, but the video worker requires a private local disk. The application
remains the authorization boundary. Public buckets and long-lived signed URLs
are not part of this contract. Direct uploads and CDN delivery would require
separate retention, object-store access and interrupted-upload cleanup rules.

## Optional short video and Reels

`VIDEO_POSTS_ENABLED=false` is the default. When enabled after operator
readiness checks, an author can save a private text draft, upload one video of
at most 64 MiB, provide an accessibility description, and publish only after
processing reaches `ready`. Video cannot coexist with a gallery or poll.
The media job probes the untrusted source, rejects unsupported or overlong
content, normalizes accepted video to H.264/AAC MP4 at up to 720p, creates a
WebP poster, then removes the source. The limit is 90 seconds. Failed uploads
remain private and can be replaced; the reconciliation command prunes stale
failed sources. One GiB per Space is reserved across pending and ready video.

Video and poster files remain on the private local media disk. Authorized
application routes support bounded byte-range requests and recheck the current
post, Space, block and moderation policy on every request. Neither a copied
playback URL nor a cached UI card grants lasting access. Reels uses the same
posts and current visibility rules as the ordinary feed, in chronological
order. Playback is paused off screen and autoplay is disabled when the viewer
prefers reduced motion. There is no recommendation algorithm, tracking,
public object bucket or long-lived signed link.

Deleting a post, draft, Space or account removes its owned video after the
database commit. A stale queued job cannot restore a deleted or replaced
video. Operators should still back up and monitor the private media disk and
run `php artisan media:videos-reconcile` to inspect stale work without changes.

### Video operator readiness

Video is deliberately not enabled by a code deploy. On the exact target
subscription, install FFmpeg with `libx264`, FFprobe and PHP GD with WebP;
provide a writable private local media disk with spare capacity. Set both PHP
CLI and PHP-FPM upload/post limits above the 64 MiB input plus multipart
overhead. Configure a persistent shared cache, an asynchronous database/Redis
queue, the Laravel scheduler, and a dedicated worker for the `media` queue.
The scheduler sends a heartbeat job each minute. Then run the read-only
`php artisan media:video-preflight` as the application user. A passing result
checks the worker heartbeat, binaries, disk, cache, queue and CLI limits;
PHP-FPM limits must be checked separately. Only then explicitly set
`VIDEO_POSTS_ENABLED=true` and reload the application configuration. Do not
run the media worker in a shared Plesk subscription without verifying the
subscription user, process limits, disk quota, backup and rollback boundary.
