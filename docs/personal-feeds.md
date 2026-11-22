# Private personal feeds

Open **My feeds** from the Community timeline tabs or desktop navigation. Name a feed, then search for and select people, Spaces or Topics. You can also start with an empty feed. Each feed shows the newest 30 visible matches in chronological order, with post ID as a stable tie-breaker. Creating, editing or deleting a personal feed does not affect Favorites, follows, saves or Space membership.

- A member can create up to 20 feeds and select up to 30 total sources per feed. Feed names are limited to 80 characters. Only the owner can list, view or change a feed.
- Selectable people must be discoverable and visible to the member. Spaces follow the Space visibility and membership rules. Topics must have at least one post the member can see. Search returns at most 12 results per source type; refine the query to find more.
- Results use the same visibility, block, mute and moderation rules as the Community timeline. A post matching multiple sources appears once. Unavailable sources disappear from the editor and cease to supply posts. If access returns, untouched selections can return; saving the editor keeps only the sources submitted with that save.
- Personal data exports include the owner's feed names and visible source references as profile handles, Space slugs and topic names, not copies of other members' posts. Deleting a feed removes its source links. Deleting a source removes its links; deleting an account removes its feeds.

The feature uses authenticated web routes under `/my-feeds`. It does not change the public API. Apply the normal Laravel migrations before opening the new routes.
