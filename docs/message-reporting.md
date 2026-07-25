# Direct-message reporting

Lineweb Social provides a deliberately narrow safety workflow for private
messages. It gives members a useful reporting path without turning platform
administration into unrestricted conversation access.

## Member contract

- Only a conversation participant may report a message, and only when the
  other participant authored it.
- A block does not remove the reporting path or existing conversation history.
- Each participant may submit one report per message.
- The reason comes from the shared allowlist. Optional member context is
  trimmed and limited to 750 characters.
- Submissions are limited to 10 per member per hour.
- The reported sender is not notified.

The report dialog states the evidence boundary before submission: the exact
message, selected reason, and member-entered context are shared with platform
administrators. The rest of the conversation is not.

## Administrator contract

The administrator queue projects only:

- the exact submitted message snapshot and original timestamp;
- the allowlisted reason and optional reporter context;
- minimal reporter and reported-member summaries; and
- the report status, reviewer history, and decision timestamp.

It never fetches or serializes adjacent messages. Administrators can mark a
report as reviewing, resolved, dismissed, or reopened. Every action requires a
10-to-500 character note and creates a `PlatformAuditLog` entry with bounded
identifiers and action metadata. Message text, reporter identity, and private
context are not copied into the general audit log.

A report decision is not an automatic account or content action. If account
access must change, an administrator must use the separate, reason-required
suspension workflow.

## Storage, export, and retention

`direct_message_reports` stores a snapshot of the exact submitted message. Its
message and user references are nullable so account or message deletion does
not silently destroy active safety evidence.

The reporter's personal JSON export includes reports they submitted and the
evidence they chose to share. It excludes administrator identity and notes.
Reports made by another person are not included in the reported member's
export.

Active reports remain available until an administrator records a decision.
Resolved and dismissed report rows are pruned after 180 days by:

```bash
php artisan message-reports:prune
```

The command is scheduled daily and supports a bounded `--days` override for a
documented deployment policy. Laravel's scheduler must be running in
production.

## Extension points

`DirectMessageReported` and `DirectMessageReportModerated` are dispatched after
their database transactions commit. Listeners that perform I/O should be
queued and idempotent. Do not send message evidence, reporter identity, or
member context to a third party without an explicit operator choice, a
documented data flow, and an appropriate privacy basis.

This workflow is a technical safety and privacy baseline, not a legal
certification. Deployers remain responsible for their notice, review and appeal
process, lawful basis, retention exceptions, backups, and incident response.
