# Extension database lifecycle

Lineweb Social runs extension database changes only from reviewed local source
and an explicit operator command. The browser remains read-only: it cannot
upload an extension, execute a migration, roll back schema, or delete extension
data.

## Contract

An extension that owns database tables declares a confined migration directory
and an explicit uninstall data policy:

```json
{
    "database": {
        "migrations": "database/migrations",
        "uninstall_data": "retain"
    }
}
```

The first contract accepts only `retain`. Removing source therefore never
silently deletes customer or community data. A future destructive uninstall
workflow needs a separate reviewed contract, an operator confirmation, and
extension-specific deletion logic.

Migration files:

- live directly inside the declared directory;
- use Laravel's timestamped migration filename convention;
- return a Laravel migration object with reversible `up` and `down` methods;
- are ordered by filename inside their owning extension;
- are limited in count and file size by core configuration;
- remain immutable after they have been applied.

The core records `extension id + migration name` as the stable identity. It also
stores the extension version, SHA-256 checksum, batch, and application time.
Migration names may overlap between different extensions without colliding.

## Deploy a reviewed extension

Back up the application database with the infrastructure's normal verified
backup process first. The command requires an explicit acknowledgement but
cannot verify an external backup:

```bash
php artisan platform:extensions
php artisan platform:extensions:migrate example-polls --backup-confirmed
```

In production, add Laravel's normal `--force` flag. Keep the extension disabled
while its schema changes, then enable its provider and rebuild configuration:

```dotenv
LINEWEB_SOCIAL_EXTENSIONS=example-polls
```

```bash
php artisan config:cache
php artisan platform:extensions
```

All selected extensions are inspected before the first migration runs.
Execution stops on the first failure. Laravel wraps a migration in a database
transaction only when the selected database grammar supports transactional
schema changes and the migration allows it. MySQL DDL is not generally atomic,
so Lineweb Social does not claim automatic rollback after a failed deploy.
Restore the verified backup or repair the reviewed migration before retrying.

## Roll back the latest extension batch

Disable the extension provider first, verify a current backup, and roll back one
extension at a time:

```bash
php artisan platform:extensions:rollback example-polls --backup-confirmed
```

In production, add `--force`. Rollback refuses to run when applied source is
missing or its checksum changed. Only the latest recorded batch for that
extension is reversed.

## Integrity and ownership states

The administrator Extension Center and `platform:extensions --json` expose:

- pending migrations that have reviewed source but are not applied;
- applied migrations whose source still matches its recorded checksum;
- blocked state when applied source changed, disappeared, escaped its declared
  directory, exceeded a bound, or the ownership registry is unavailable;
- retained data when an extension has been removed while its migration records
  remain.

No absolute server paths or migration source are sent to the browser. The
ownership registry is operational metadata; extension-created tables and their
privacy obligations remain owned by that extension.

## Deliberate limits

- no migration execution or rollback from HTTP routes;
- no automatic backup, restore, or rollback claim;
- no cross-extension dependency ordering;
- no browser activation, ZIP upload, remote download, or marketplace updater;
- no asset pipeline or JavaScript execution contract;
- no destructive uninstall or data purge.

These limits keep the first executable lifecycle auditable and reversible at
the deployment boundary instead of pretending arbitrary PHP is sandboxed.
