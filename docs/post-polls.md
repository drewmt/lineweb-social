# Post polls

Lineweb Social includes a deliberately small poll primitive for communities
that need a decision, not an engagement mechanic. A poll can accompany text
and a gallery, or stand alone as a post.

## Product boundary

- A poll has one question (up to 180 characters) and two to four distinct
  answers (up to 100 characters each).
- It can remain open indefinitely or close one, three, or seven days after
  publication. A draft never starts its close timer.
- A current Space member can select one answer and change it while the poll is
  open. The server locks the poll post and retains one row per member, so a
  repeated request cannot create duplicate votes.
- A published poll is immutable. This protects the meaning of already-cast
  votes; the author may still delete the post through the normal ownership and
  moderation rules.
- Polls use the same post visibility, mute, mutual-block, hidden-content, and
  Space-membership rules as the surrounding community. Being able to see a
  public Space does not grant a vote.

## Results and privacy

Before an eligible member votes, totals and percentages stay hidden to avoid
herd effects. The author can see aggregate results, as can any viewer once the
poll closes. The web and API projections include only the current viewer's
selected option plus aggregate counts; they never expose voter names, handles,
vote timestamps, or a voter list.

`GET /api/v1/feed` and `GET /api/v1/posts/{post}` expose a nullable `poll`
field. The API is intentionally read-only in this first iteration: native
write endpoints are not advertised until they have their own scoped contract,
authorization, throttling, and test coverage.

## Storage and lifecycle

`post_polls`, `post_poll_options`, and `post_poll_votes` are owned by the post
and cascade when it is removed. Options are always created transactionally with
their poll. No private state or browser-only vote record is trusted as the
source of truth.

## Extension guidance

Extensions may render the documented aggregate projection, but should not read
poll-vote rows directly or infer member choices. Larger formats—multiple-choice
voting, anonymous surveys, weighted votes, exports, or prize draws—belong in a
separately reviewed extension with its own retention, abuse, and data-rights
contract.
