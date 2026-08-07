# Notifications

The notification core is intentionally calm and useful. It alerts a member
when another member replies to their post or top-level comment, or directly
mentions their handle,
and alerts eligible Space owners or moderators when a new post or comment
report needs attention.

## Categories and delivery

| Preference | Trigger | Recipient |
| --- | --- | --- |
| `comment_replies` | `CommentPublished` | The parent-comment author for a direct reply; otherwise the post author |
| `content_mentions` | `PostPublished`, `CommentPublished`, or an author edit | Up to ten visible mentioned members, excluding the author |
| `space_moderation` | `PostReported` or `CommentReported` | Current Space owners and moderators, excluding the reporter |

All preferences default to enabled and affect new notifications only. Mention
handles are case-insensitive, deduplicated, and bounded to ten per body. Editing
content alerts only newly added handles. The ordinary recipient of a comment or
direct-reply alert receives that notification instead of a second mention alert,
unless reply alerts are disabled and mention alerts remain enabled.

In-app delivery uses Laravel's database channel synchronously, so the core
experience does not require a queue worker. Per-reaction notifications remain
deliberately unavailable. Typed post reactions
emit `PostReactionChanged` for extensions, but the core deliberately avoids a
notification for every reaction.
Follow changes similarly emit `UserFollowChanged` for extensions, but the core
does not create a notification for every follow in this release.

## Daily email digest

Email delivery is separately opt-in. `email_digest_frequency` accepts `off`
(the default) or `daily`. Enabling it records a private delivery cursor at that
moment, so old notification history is not unexpectedly mailed.

The scheduled `notifications:dispatch-digests` command runs at 08:00 in the
application timezone (UTC by default) and queues one unique
`SendDailyNotificationDigest` job per eligible member. A worker must process the
`notifications` queue. The scheduler uses `onOneServer`; multi-server
deployments therefore need a shared cache that supports atomic locks.

Each job repeats the current checks before handing data to the configured mail
transport:

- the member still has a verified email and an active account;
- daily delivery is still enabled;
- the notification remains unread and its destination remains authorized; and
- the stored item is a known core notification type.

The email contains only aggregate counts for replies, mentions, and moderation
alerts plus a link to the authenticated notification inbox. It never contains
post or comment text, member names, Space names, report details, reporter
identity, or a stored destination URL. Authorization runs again inside the web
inbox.

A digest processes at most 100 candidate rows. When a window is larger, a
timestamp-and-notification cursor preserves the remaining backlog for the next
run instead of dropping it. Empty or fully stale windows advance without sending
mail. The cursor advances only after the mail transport returns successfully.
Generic email cannot provide exactly-once delivery: a provider timeout after
acceptance may cause a retry and a rare duplicate. The job retries three times
and never blocks post, comment, report, or notification writes.

Turning delivery off prevents jobs that have not begun sending from continuing.
An email already accepted by an external provider cannot be recalled. Web push,
mobile push, instant email, and per-member delivery times are not included.

## Privacy and authorization

Notification rows store stable identifiers, not post or comment excerpts,
report details, or reporter identity. Mention links are also resolved for the
current viewer instead of being stored in rendered content. The presentation
layer rechecks the same policies, profile visibility, Space access, mute
relationships, and block relationships used by the destination itself.
Direct replies may store `reply_to_comment_id` solely to preserve their
notification context; top-level notification payloads remain unchanged.

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

New channels should be queued when they perform I/O, tolerate retries, respect
the member's consent and notification preferences, and repeat authorization at
delivery time. Do not copy report details, private content, or reporter identity
into third-party services without a deliberate administrator choice and an
appropriate privacy basis.
