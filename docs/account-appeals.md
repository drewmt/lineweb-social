# Account status and appeals

Lineweb Social gives restricted members one clear account-status surface and one
human-reviewed appeal for each distinct restriction cycle. The workflow is part
of the platform account-access boundary; it does not replace Space moderation
or the evidence-limited message-report queue.

## Member boundary

Authenticated members can always open `/account-status`. An active member sees
their current standing and latest review history. A restricted member sees:

- when the restriction began;
- which community capabilities are paused;
- the current appeal state and their own submitted statement;
- a member-visible administrator response, when one exists; and
- the existing password-confirmed personal export and account-deletion paths.

The member projection deliberately excludes the internal suspension reason,
acting or reviewing administrator identity, reporter identity, private safety
evidence, and audit context.

## One appeal per restriction

Every suspension receives a random internal `suspension_reference`. An appeal
stores that reference and enforces it as unique, so the account holder cannot
submit duplicate appeals for the same restriction. A later, separate suspension
gets a new reference and may receive a new appeal.

Appeal statements are trimmed, limited to 20–2,000 characters, and accepted
through a dedicated per-account rate limit. Submission locks the member record
before validating the current restriction and appends a bounded
`appeal.submitted` audit event without copying the statement into the audit log.

## Human review

The protected `/admin/appeals` workspace shows the member statement beside the
internal restriction record. Only an active, verified platform administrator
can change appeal state.

The first workflow supports:

- `open` → `reviewing`;
- `open` or `reviewing` → `approved`; and
- `open` or `reviewing` → `denied`.

Every action requires a 10–500 character message that is explicitly visible to
the member. Operators must not copy private report evidence, reporter
identities, credentials, or unrelated personal data into this message.

Approval clears the restriction inside the same database transaction and
appends both `appeal.approved` and `member.reinstated` audit entries. Denial
leaves the restriction active. There is no automated approval, denial, account
action, or AI enforcement in this workflow.

If an administrator restores access directly from the member directory while a
matching appeal is active, the same transaction closes the appeal as approved
with a generic member-safe response. The internal reinstatement reason remains
in the privileged audit trail and is not exposed to the member.

Final decisions cannot be reopened or overwritten from the first-version web
queue. A deployer that needs escalation or multiple review tiers should add an
explicit state and permission contract rather than mutating past decisions.

## Data rights and retention

A member’s personal JSON export includes their appeal statement, state,
member-visible decision message, and relevant timestamps. It excludes
administrator identity, internal suspension reason, and privileged audit
context.

Appeals are deleted when their owning account is deleted. No automatic appeal
retention or archival schedule is claimed in this first version. Deployers must
document their public review process, response expectations, legal basis,
retention, escalation route, and backup lifecycle.
