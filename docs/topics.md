# Topics and hashtags

Lineweb Social turns bounded hashtags in post bodies into privacy-safe topic
trails. Topics improve discovery without creating an algorithmic feed or
publishing global engagement data.

## Parsing and storage

- A post can index at most ten unique topics, in body order.
- Topic names are case-insensitive, normalized to lowercase, and contain 2–50
  Unicode letters or numbers with optional internal underscores or hyphens.
- Embedded word fragments and common URL-fragment forms are ignored.
- Publishing and author editing synchronize the normalized topic relation in
  the same database transaction as the post body.
- The schema migration backfills existing posts in bounded chunks.
- Deleting a post cascades its topic relationships. Topic records do not
  contain author, Space, visibility, or engagement data.

## Visibility contract

Topic pages and search results start from the same current post-visibility
query as the community feed. They exclude:

- drafts and moderated posts;
- posts in Spaces the viewer cannot currently discover;
- posts by members the viewer muted or blocked; and
- posts by members who currently block the viewer.

Counts are viewer-specific visible-post counts, never global totals. A topic
name or exact topic URL does not grant access to any post, Space, profile, or
media. Topic pages remain chronological.

## Web and API surfaces

Recognized topics are linked consistently in feed cards, post permalinks, and
profile activity. Global search can return topic matches only when at least one
matching post is currently visible to the viewer.

Read-only post resources expose an additive `topics` array containing only the
normalized name and web topic URL. API consumers must still treat the parent
post as the authorization boundary.

Topic following, trending ranks, recommendations, notifications, and comment
hashtags are intentionally outside this first contract.
