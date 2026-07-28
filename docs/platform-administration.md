# Platform administration

Lineweb Social includes a small, fail-closed operational surface for platform
owners. It is separate from Space moderation: Space owners and moderators
manage their communities, while platform administrators manage account access
across the deployment.

## Operator control center

The protected `/admin` workspace uses its own responsive shell instead of the
member-facing social navigation:

- **Overview** shows real deployment metrics, actionable account and private
  message-safety queues, recent privileged activity, and the explicit boundary
  between platform operations and Space moderation.
- **Members** provides bounded account search, status filters, current
  restriction context, and the existing reason-required suspend/reinstate
  controls.
- **Appeals** provides a dedicated human-review queue for member account-access
  appeals and explicit approve-or-deny decisions.
- **Safety** contains the evidence-limited direct-message report workflow
  described below.
- **Extensions** reports deploy-time manifest, core compatibility, explicit
  activation state, pending/applied migration ownership, source integrity, and
  retained data plus unpublished/published/blocked browser releases without
  offering uploads or executable browser actions.
- **Audit** provides a paginated, searchable, category-filtered read-only view
  of privileged actions.

The desktop sidebar can collapse without removing access to any section. On
mobile it becomes a modal navigation drawer with an explicit return to the
community. These are navigation improvements only; every page and mutation
still passes through the same server-side administrator middleware and
transactional authorization checks.

The Extensions surface is informational. Source installation, activation,
backup, migration, and rollback remain infrastructure deployment actions. Their
full boundaries are documented in [`extensions.md`](extensions.md),
[`extension-migrations.md`](extension-migrations.md), and
[`extension-assets.md`](extension-assets.md).

## Bootstrap an administrator

Administrator access cannot be granted from the web interface or public API.
Use a trusted application shell with an existing verified member:

```bash
php artisan platform:administrator owner@example.com
```

To revoke access:

```bash
php artisan platform:administrator owner@example.com --revoke
```

The last administrator cannot be revoked or delete their account. Bootstrap at
least two controlled administrator accounts before operating a public service,
and protect both with passkeys or two-factor authentication.

## Authorization boundary

The first role contract contains only `member` and `administrator`.
`platform_role` is not mass assignable. The dashboard and mutation routes
require authentication, email verification, an active account, the explicit
administrator middleware, and a dedicated rate limit.

Suspension and reinstatement also recheck the administrator inside the
transaction after locking both actor and subject rows in stable order. An
administrator cannot suspend themselves or another administrator through the
web surface.

Do not treat a Space moderator as a platform administrator. If a future
operator or support role is required, add a distinct permission contract and
tests instead of broadening the current role.

## Message safety queue

Administrators can review direct-message reports through the private Message
safety queue. The queue exposes only the exact message submitted as evidence,
the reporter's allowlisted reason and optional context, and the two relevant
member summaries. It never exposes adjacent messages or the full conversation.

Every review, resolution, dismissal, or reopening requires a 10-to-500
character operator note and appends a bounded platform audit entry. Resolving a
message report records the safety decision; it does not automatically suspend
an account or delete content. Account access remains a separate, explicit
administrator action.

Closed report evidence is pruned after 180 days by the scheduled
`message-reports:prune` command. Operators must run Laravel's scheduler and
document any deployment-specific retention changes. See
[`message-reporting.md`](message-reporting.md) for the complete boundary.

## Suspension behavior

Every suspension requires a reason of 10 to 500 characters. A successful
suspension:

- records the suspension time, reason, and acting administrator;
- rotates the account remember token;
- deletes all database-backed web sessions;
- revokes all personal API tokens; and
- appends a `member.suspended` audit entry.

The shared `account.active` middleware blocks the suspended member from
community web routes and authenticated API routes. Web navigation ends at the
restricted-account screen; API requests receive the stable forbidden response.

Suspension is an access decision, not automatic content takedown. Existing
public content remains subject to its Space visibility and moderation rules.
Use the normal report and moderation workflow when content itself must be
reviewed or hidden.

Reinstatement also requires a recorded reason and appends a
`member.reinstated` entry before restoring access. Previously revoked tokens and
sessions are not recreated.

## Account appeals

Each suspension receives a private random reference that defines one restriction
cycle. A restricted member may submit one bounded appeal for that cycle from
the Account Status screen. The administrator queue shows the member’s statement
and internal restriction record together, but the member never receives the
internal reason, reviewer identity, reporter identity, or private evidence.

Moving an appeal into review, approving it, or denying it requires a
member-visible message and appends a bounded audit event. Approval explicitly
restores access in the same transaction; denial leaves the restriction active.
There is no automated enforcement or decision-making. Direct reinstatement from
the Members surface also safely resolves a matching active appeal so operators
cannot leave a stale review behind.

The full state, projection, export, and first-version limits are documented in
[`account-appeals.md`](account-appeals.md).

## Data-rights boundary

The Account Status screen preserves the password-confirmed personal export and
account-deletion paths for restricted members. Email verification and existing
Space ownership guards still apply. If a suspended member owns a Space
containing another person's activity, the deployer must provide a process to
review access and complete ownership transfer before deletion.

This is a technical safeguard, not a privacy-law certification. Deployers still
own notices, retention, support, escalation, backup deletion, and statutory
response procedures.

## Audit trail

Privileged actions are stored in `platform_audit_logs` with nullable actor and
subject references so the record can survive later account deletion. The
Overview shows the latest entries and the dedicated Audit surface provides
paginated category filters plus bounded member/operator/reason search.
Retention, archival, and protected operator exports are intentionally not
implemented yet.

Core code must append audit records through `PlatformAdministration`. Do not
add edit or delete controls for audit rows. Extensions that introduce new
privileged actions should define a bounded action enum, record only necessary
context, and avoid secrets or unrelated personal data.

## Deliberate first-version limits

- no web or API administrator promotion;
- no generic role editor or implied moderator hierarchy;
- no remote administrative API;
- no automatic content deletion during account suspension;
- no automated appeal decisions or multi-tier appeal escalation;
- no audit export or privileged report export; and
- no claim that administrator access replaces infrastructure access controls.

These limits keep the first contract reviewable and give downstream products a
clear authorization boundary to extend.
