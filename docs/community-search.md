# Community search

Verified, active members can search currently accessible posts, Spaces, people
and topics at `/search`. The existing member throttle applies to every page.

## Overview and focused results

- `q` accepts at most 100 characters and is whitespace-normalized. Queries shorter
  than two Unicode characters show a starting state without pagination.
- `type` is one of `all`, `posts`, `spaces`, `people` or `topics`. The default
  overview shows up to eight matches per category. A category link opens its
  focused results without searching unrelated categories.
- Focused views show eight results per page, with previous/next links that keep
  the normalized query and category in the URL. Changing category or submitting
  a new query starts at page one. Browser back/forward restores the matching
  query and view. No client-side search-history store is introduced.
- Pagination fetches one additional policy-filtered record to determine whether
  a next page exists. It does not run a global result-count query.
- `page` must be an integer from 1 through 1000. At the bounded browsing limit,
  members are prompted to narrow their query. The overview ignores `page`.
- Posts remain ordered by publication time and ID descending. Spaces and people
  sort by name then ID. Topics sort by currently visible post count, name and ID.
  Results are not a frozen snapshot: membership, moderation, blocking and newly
  published content may change what appears between page requests.

## Visibility and privacy

Every page applies the existing post, Space, discovery and relationship policies
before its offset and limit. Drafts, hidden content, inaccessible Spaces and
blocked authors cannot fill slots or leak through pagination. Topic counts
include only posts the current member can see. People retain the existing
discovery opt-out and profile visibility rules; muting does not itself hide a
person from the People directory.

Payloads remain explicit presentation fields, not serialized member records.
No email address, moderation detail or global private-content count is added.
Search terms are present in navigable URLs, so normal browser history and
operator access-log policies still apply. No search analytics or retention
system is introduced by this feature.

The mobile category rail scrolls within its container, the active link uses
`aria-current`, and pagination controls have explicit labels and disabled
states. Empty later pages keep a route back to page one when content or access
changes. No external search service, schema migration or API v1 change is needed.
