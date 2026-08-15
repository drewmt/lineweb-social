# Starter community bootstrap

Lineweb Social can prepare a useful first Space for an existing member without
creating shared demo credentials or exposing a setup endpoint. Run the command
from a trusted application shell after the member has registered and verified
their email:

```bash
php artisan platform:starter-community owner@example.com --confirm
```

The default blueprint creates a private **Community HQ**, makes the member its
owner, publishes three practical welcome/introductions/roadmap prompts, and adds
the welcome post to Space highlights. The operation writes an append-only audit
marker and is idempotent: repeating it for the same member returns the existing
starter community without duplicating or rewriting content.

The name and visibility can be selected explicitly:

```bash
php artisan platform:starter-community owner@example.com \
  --name="Customer Community" \
  --visibility=private \
  --confirm
```

Supported visibility values are `public`, `private`, and `hidden`.

## Safety boundary

- The member must already exist, have a verified email, and have an active
  account.
- `--confirm` is always required, including in non-interactive automation.
- The command creates no user, password, token, API credential, or administrator
  privilege.
- It never resets a database, deletes content, or injects posts into an existing
  unmarked Space.
- The idempotency marker belongs to the created Space and is removed naturally
  if that Space is deliberately deleted.

This bootstrap is a starting point, not a permanent product decision. The owner
can edit or remove the starter posts, invite members, publish new content, and
shape the community normally after provisioning.
