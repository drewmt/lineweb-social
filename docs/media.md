# Post image contract

Lineweb Social treats uploaded media as untrusted content. The first media slice
supports one optional image on a text post. It is intentionally narrower than a
gallery or video system so storage, privacy, accessibility, and deletion behavior
can stabilize before extensions depend on them.

## Current scope

- Post text remains required.
- A post may contain one JPEG, PNG, or WebP upload of at most 8 MiB.
- Alternative text is required when an image is attached and is limited to 300
  characters.
- The decoded source may contain at most 12 million pixels.
- The stored result is a single static WebP image, at most 2,048 pixels on its
  longest edge.
- Galleries, animated images, video, audio, remote URL imports, direct-to-cloud
  uploads, editing, and replacement are outside this release.

## Upload trust boundary

File extensions and browser-provided content types are not trusted. The server
checks the detected MIME type, parses image dimensions before full decoding,
rejects unsupported or oversized input, and then decodes the image with GD.

The original upload is never stored. The decoded pixels are orientation-corrected,
resized when needed, and re-encoded as WebP under a generated name. This removes
the original filename, EXIF/GPS metadata, embedded profiles, animation, and data
appended to an otherwise valid image. A decoder or encoder failure rejects the
whole post instead of keeping a partially trusted file.

## Ownership and authorization

The image is owned by its post and inherits the post's author, Space, visibility,
mute/block, publication, and moderation rules. Metadata uses an explicit foreign
key to the post; the public projection never exposes a storage disk or path.

Files live on the configured private media disk. They are not symlinked into the
web root and do not receive public object-store URLs. An authenticated controller
re-authorizes the parent post on every request, verifies the stored object still
exists, and serves only the normalized WebP response with private caching,
`nosniff`, and same-origin resource protection.

Hiding a post immediately makes its image unavailable to ordinary members while
retaining author and moderator access through the existing policy. Deleting a
post, Space, or account removes the stored object as well as its database row.

## Accessibility and presentation

Alternative text is member-authored; the core does not invent descriptions from
filenames or AI. Width and height are persisted and included in server-produced
view models so clients can reserve the correct aspect ratio and avoid layout
shift. Feed, permalink, and profile surfaces reuse the same image contract.

## Abuse and operational limits

The existing per-member publishing limiter also bounds image-processing work.
Input bytes, decoded pixels, output dimensions, and attachments per post are all
limited independently. Deployments that enable media must provide PHP GD with
WebP support plus the EXIF and Fileinfo extensions. PHP's
`upload_max_filesize` must allow 8 MiB and `post_max_size` must also leave room
for the surrounding multipart request; 10 MiB or more is a practical baseline
for this one-image contract.

Operators may point `MEDIA_DISK` at another configured private Laravel disk, but
the application remains the authorization boundary. Public buckets and URLs are
not supported by this contract. Storage errors fail closed and do not publish a
post without its requested image.

Future media work should build on this boundary rather than relaxing it. Before
adding galleries, video, direct uploads, or CDN delivery, define per-Space quotas,
asynchronous processing states, malware handling, retention, object-store access,
and cleanup of interrupted multipart uploads.
