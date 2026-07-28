# Privacy and data rights

Lineweb Social provides a technical baseline for member data access and
self-service account deletion. Deployers remain responsible for their own
privacy notice, retention schedule, legal basis, processor agreements, backup
lifecycle, and response process. These features are not a compliance
certification.

## Personal data export

Verified members can download a JSON export from Profile settings after recent
password confirmation. The route is rate limited and returns a private,
non-cacheable streamed response.

The export currently includes:

- profile and account fields, including platform role and suspension timestamp;
- Space memberships and owned Space metadata;
- published posts and private drafts, including publication state and safe image
  metadata, plus comments, reactions, saves, follows, and safety relationships;
- messages authored by the member;
- notification metadata and notification preferences, including the member's
  daily-email choice but not the internal delivery cursor;
- invitation and moderation activity without recipient addresses or internal
  audit context;
- reports submitted by the member, including the exact direct-message snapshot
  they chose to submit;
- account appeals, including the member statement, state, member-visible
  response, and relevant timestamps; and
- safe security metadata for two-factor state, API tokens, passkeys, and active
  sessions.

Collections are emitted in chunks and are not capped. A large account therefore
does not silently receive only the first page of its content.

The export deliberately excludes:

- password hashes, two-factor secrets and recovery codes;
- API token digests, passkey credentials, invitation token hashes, and session
  identifiers or payloads;
- private storage paths, media checksums, and original filenames;
- messages authored by another participant unless the member explicitly
  submitted that exact message in a safety report; and
- internal notification payloads, reviewer identities, and private moderation
  or account-restriction context.

Extensions that store member data must add their own export section or document
why the data is not portable. Do not place extension secrets or unrelated
third-party personal data in the core export.

## Account deletion

Account deletion requires the current password. On successful deletion, the
member is logged out, their session is invalidated, API tokens and notifications
are removed, and user-owned records are removed through the database ownership
rules. Private post media owned by the account is deleted from configured
storage.

An account cannot be deleted while it owns a Space that contains another
member or another person's community activity, including content, moderation
records, or invitations. The Profile settings page links to each blocking Space
so ownership can be transferred first. The same condition is rechecked inside
the deletion transaction to protect against a stale browser page.

A sole-owner Space with no other person's content is deleted with the account.
This is intentional, but deployers should make the consequence clear in their
own product copy.

Suspended accounts cannot use community or API features, but their
password-confirmed export and deletion routes remain available from the
Account Status screen. Existing Space ownership guards still apply. When a
suspended member owns a blocking Space, the operator needs a documented process
to review the suspension and help complete an ownership transfer before
deletion. Suspension is not a substitute for a data-rights response process.

An account appeal belongs to the member and is removed with that account. The
member’s export includes their own statement and the response intentionally
shown to them, but excludes the reviewing administrator, internal suspension
reason, and privileged audit context. See
[`account-appeals.md`](account-appeals.md) for the complete projection boundary.

Direct-message report evidence may survive deletion of the original message or
either account so an active safety review is not silently destroyed. Active
reports are retained until an administrator records a decision; resolved and
dismissed report evidence is pruned after 180 days. A reporter's export excludes
the reviewer identity and private decision note, and a reported member does not
receive another person's report record through their export.

## Operator responsibilities

Application deletion does not erase independent backups, infrastructure logs,
mail-provider records, analytics, or data copied to an extension or external
processor. A deployed service needs documented retention and deletion
procedures for every such system.

Extension migration ownership is tracked by extension ID, but the first
uninstall data policy is deliberately `retain`: removing provider source does
not erase extension-owned tables. Operators must keep the source needed for
export, correction, rollback, and lawful deletion until a separately reviewed
destructive uninstall process exists.

Laravel's scheduler must run for closed direct-message report evidence to be
pruned and for daily notification digests to be queued. Digest delivery also
requires a queue worker and a deliberately configured email provider. Deployers
must document their lawful basis, processor relationship, safety-review process,
appeal route, retention schedule, and any legally required preservation before
changing the default.

Before adding analytics, advertising, AI processing, or third-party exports,
map the new data flow and update both this contract and the deployer's public
privacy information.
