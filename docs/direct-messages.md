# Direct messages

Lineweb Social's first messaging slice is a private, chronological
conversation between exactly two verified members.

## Access and privacy

- A member may start a conversation only from a profile they can currently
  view. Private, unshared, self, or mutually blocked profiles are rejected.
- The ordered member pair is unique, so sending from either direction reuses
  the same conversation instead of creating duplicates.
- Only those two participants may list, open, send to, or mark the conversation
  as read. View models expose names, handles, message text, timestamps, and the
  current viewer's own/unread state; emails and relationship internals are not
  serialized.
- Every send locks and re-authorizes the conversation. A block in either
  direction stops new delivery. Existing history remains visible to both
  participants so blocking does not silently destroy personal records or
  potential abuse evidence.

Muting does not block direct messages. Members who need to stop delivery must
use Block.

## Storage and read state

`conversations` stores the canonical member pair, each participant's last-read
message ID, and the latest-message pointer used for bounded inbox queries.
`direct_messages` stores the sender, body, and timestamps under a cascading
conversation foreign key.

Opening a thread does not mutate data through `GET`. The web client follows
with an idempotent, participant-authorized `POST` to advance only the current
member's read pointer.

The inbox returns the 50 most recent conversations and a thread returns its 50
most recent messages. The UI states this limit rather than implying that older
history was deleted.

## Abuse and product limits

- Message bodies are trimmed, required, limited to 2,000 characters, and
  throttled to 30 mutation requests per member per minute.
- Empty conversations are not persisted; the first row is created only when a
  valid first message is sent.
- This release has no attachments, groups, editing, deletion, typing presence,
  realtime transport, delivery receipts, email/push delivery, or public API.
- Messages are access-controlled at the application layer but are not
  end-to-end encrypted. Server operators retain normal database access and the
  interface says so explicitly.

Future retention, export, reporting, and moderation work must preserve the same
participant boundary and document any operator visibility or deletion
exceptions before those capabilities are presented as complete.
