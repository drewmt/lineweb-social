# Content moderation extension points

The moderation core gives each Space an accountable workflow for member reports. Posts and comments deliberately use separate foreign-key-backed report tables while sharing the same reason, status, and action enums. This keeps database integrity explicit without duplicating the workflow semantics.

This contract is pre-release and may receive additive changes before the first tagged preview.

## Lifecycle

1. A verified member can report visible content they did not author.
2. The server validates an allowlisted reason, enforces Space and block visibility, rate-limits submissions, and permits one report per member and content item.
3. Space owners and moderators can move an open report into review, hide the content, or dismiss the report.
4. Hiding removes the content from community surfaces. Reopening restores it only when no other resolved report still requires it to remain hidden.
5. Review, hide, dismiss, and reopen actions append a `SpaceAuditLog` entry with report and content identifiers. Reporter identity is not copied into the general audit log.
6. Authors may edit or delete their own visible content, except while any report
   is open or under review. This prevents content or evidence from changing
   during an active moderation decision.

Current states are `open`, `reviewing`, `resolved`, and `dismissed`. Current moderator actions are `review`, `hide`, `dismiss`, and `reopen`.

## Domain events

Extensions can react after the database transaction succeeds:

- `App\Events\PostReported` and `App\Events\CommentReported` expose a created report.
- `App\Events\PostReportModerated` and `App\Events\CommentReportModerated` expose the updated report and the `ReportAction` that produced the change.
- `App\Events\PostPublished` and `App\Events\CommentPublished` expose newly published conversation content.

Register listeners through Laravel's normal event discovery or an extension service provider:

```php
use App\Events\CommentPublished;
use Illuminate\Support\Facades\Event;

Event::listen(CommentPublished::class, function (CommentPublished $event): void {
    // Queue notifications or update an extension-owned index.
    // Do not perform slow network work inside the web request.
});
```

Listeners should be idempotent and queued when they perform I/O. Treat report details and reporter identity as sensitive community data; do not send them to third parties without an explicit administrator choice and an appropriate privacy basis.

## Authorization and integrity boundaries

- `PostPolicy` controls post visibility, commenting, reporting, author editing,
  and deletion.
- `CommentPolicy` controls comment visibility, reporting, author editing, and
  deletion.
- `SpacePolicy::moderate` controls queue access and decisions.
- Form Requests and transaction-backed domain services both enforce authorization.
- Moderator routes use scoped nested binding; services repeat Space/report/content relationship checks under row locks.
- Reason and action values are backed by shared enums; clients cannot invent workflow states.
- Report `space_id` values are derived from content on the server and are never accepted from client input.
- Post and comment publishing/reporting use separate per-user rate limiters;
  author edit/delete mutations share a dedicated per-user limiter.

Frontend visibility is only a convenience. Extensions must never treat a hidden button or a client-provided Space identifier as authorization.

## Adding another reportable content type

Prefer an explicit table and foreign key until content types share a proven lifecycle. Reuse the shared enums only when their semantics are genuinely identical. Add all of the following together:

1. a policy that enforces visibility, authorship, and safety relationships;
2. Form Requests with allowlisted reasons and bounded context;
3. a transaction-backed service that repeats authorization and tenant checks;
4. a private moderator projection scoped to one Space;
5. append-only audit entries and after-transaction events;
6. feature tests for unauthorized, duplicate, cross-Space, invalid-transition, hide, and restore paths.

A generic polymorphic report table is deliberately not the default: without enforced morph aliases and database-level target integrity, it makes orphaned records and cross-tenant mistakes easier.
