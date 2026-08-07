# Conversation and reply contract

Lineweb Social supports chronological comments plus one bounded level of direct
replies. The limit is deliberate: it gives members clear conversational context
without recursive payloads, deeply indented mobile layouts, or a second ordering
model that would conflict with the timeline.

## Publishing invariants

A top-level comment has no `parent_id`. A direct reply stores the visible
top-level comment it answers in `parent_id`.

Comment publishing runs through one transaction-backed service. Under row locks,
the service repeats post authorization and verifies that a requested parent:

- belongs to the same post;
- is published, visible, and not moderated;
- is itself a top-level comment; and
- was not authored by someone the current member muted or mutually blocked.

The client cannot create a reply to a reply or move a reply across posts. The
existing per-member comment publishing limit covers both comments and replies.

## Ordering and pagination

Replies remain in the same flat `(published_at, id)` chronological stream as
all other comments. Web pagination, API cursors, notification anchors, visible
comment counts, and extension consumers therefore keep one deterministic
ordering contract.

The web and API add only safe reply context: whether the comment is a reply and,
when the current viewer can still see the parent, the parent's identifier and
public author identity. Parent body content is never copied into a reply payload.
If the parent is hidden, muted, blocked, deleted, or otherwise inaccessible,
the reply remains visible without parent context.

## Editing, deletion, and moderation

Every reply is an ordinary authored comment for permissions, reporting, editing,
and moderation. A report or moderation decision targets only that exact comment.
Hiding a parent does not automatically hide replies written by other members.

Deleting a parent sets each direct reply's `parent_id` to `null`; it never
cascade-deletes another member's contribution. Deleting the post still removes
the complete conversation through the post foreign key.

## Notifications and privacy

A top-level comment notifies the post author. A direct reply notifies the parent
comment author instead, with self, preference, mute, block, and current-access
checks. The post author does not receive an additional reply alert.

Reply notification rows contain identifiers only. A direct reply may add
`reply_to_comment_id`, but no post text, comment text, member name, or rendered
destination is stored. Mention delivery is deduplicated against the ordinary
reply recipient; when reply alerts are disabled, an enabled explicit mention
can still provide the fallback alert.

## Extension guidance

Extensions listening to `CommentPublished` should treat `parent_id` as nullable
and should not assume the referenced parent will remain available. Preserve the
flat chronological order unless an extension owns a separate presentation and
pagination contract. Never load or serialize a parent before applying the
current viewer's visibility rules.
