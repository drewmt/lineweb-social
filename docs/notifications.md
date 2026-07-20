# In-app notifications

The first notification slice is intentionally small and useful. It alerts a
member when another member replies to their post and alerts eligible Space owners
or moderators when a new post or comment report needs attention.

## Categories and delivery

| Preference | Trigger | Recipient |
| --- | --- | --- |
| `comment_replies` | `CommentPublished` | The post author, unless they wrote the reply |
| `space_moderation` | `PostReported` or `CommentReported` | Current Space owners and moderators, excluding the reporter |

Both preferences default to enabled and affect new notifications only. Delivery
uses Laravel's database channel synchronously, so the core experience does not
require a queue worker. Email, web push, mobile push, digests, mentions, and
reactions are not part of this release.

## Privacy and authorization

Notification rows store stable identifiers, not post or comment excerpts, report
details, or reporter identity. The presentation layer resolves those identifiers
at request time and rechecks the same policies, profile visibility, Space access,
mute relationships, and block relationships used by the destination itself.

Opening a notification is a `POST` action. The server confirms that the
notification belongs to the authenticated member, resolves its current safe
destination, and then marks it read. Deleted, hidden, inaccessible, or unknown
targets render as unavailable and do not reveal their previous content.

Reply destinations are calculated against the current visibility-filtered
conversation. If later replies move the referenced comment onto an older page,
the notification still links to the page and anchor that currently contains it.

## Extension guidance

Core listeners are discovered through Laravel's event discovery and remain
separate from controllers and React pages. Extensions may listen to the same
after-transaction domain events to add an explicitly configured transport or an
extension-owned category.

New channels should be queued when they perform I/O, remain idempotent, respect
the member's consent and notification preferences, and repeat authorization at
delivery time. Do not copy report details, private content, or reporter identity
into third-party services without a deliberate administrator choice and an
appropriate privacy basis.
