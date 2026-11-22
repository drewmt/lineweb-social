# Saved post collections

**Saved** remains a private reading list. Use **New collection** to make a
folder, then choose **Collections** on a saved post to put it in any number
of your folders. Choose **All saved** to show bookmarks regardless of folder.
Collections can be renamed or deleted; deleting a folder keeps its posts
bookmarked and keeps assignments to other folders. Unsaving removes the
bookmark and all its folder assignments.

Verified, active members can maintain up to 100 collections each. Names are
trimmed and must be 1 to 80 characters. The all-saved and collection views
reapply current post visibility before selecting 30 posts per page. They order
posts by bookmark time, newest first, with bookmark ID breaking ties; adding
a saved post to a folder does not change this order.

Collection data is not part of the public API. The password-confirmed personal
data export includes the member's `saved_collections` and their assignment
references in `saved_collection_posts`, even if a bookmarked post is no longer
visible. References include IDs and assignment time only; no post content or
another member's collection is included. Existing `saved_posts` export
behavior is unchanged.

Run the standard database migrations before serving the updated application.
The migration adds two tables without rewriting existing bookmarks. Rolling
back this migration drops the new collections and assignments only.
