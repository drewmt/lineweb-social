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

- profile and account fields;
- Space memberships and owned Space metadata;
- posts, safe image metadata, comments, reactions, saves, follows, and safety
  relationships;
- messages authored by the member;
- notification metadata and notification preferences;
- invitation and moderation activity without recipient addresses or internal
  audit context;
- reports submitted by the member; and
- safe security metadata for two-factor state, API tokens, passkeys, and active
  sessions.

Collections are emitted in chunks and are not capped. A large account therefore
does not silently receive only the first page of its content.

The export deliberately excludes:

- password hashes, two-factor secrets and recovery codes;
- API token digests, passkey credentials, invitation token hashes, and session
  identifiers or payloads;
- private storage paths, media checksums, and original filenames;
- messages authored by another participant; and
- internal notification payloads, reviewer identities, and private moderation
  context.

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

## Operator responsibilities

Application deletion does not erase independent backups, infrastructure logs,
mail-provider records, analytics, or data copied to an extension or external
processor. A deployed service needs documented retention and deletion
procedures for every such system.

Before adding analytics, advertising, AI processing, or third-party exports,
map the new data flow and update both this contract and the deployer's public
privacy information.
