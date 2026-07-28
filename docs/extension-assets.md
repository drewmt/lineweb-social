# Extension browser asset lifecycle

Lineweb Social publishes extension browser assets only from reviewed local
source and an explicit deployment command. The administrator surface remains
read-only: it cannot upload, build, publish, activate, or delete extension code.

## Manifest contract

An extension may declare pre-built CSS and ES modules:

```json
{
    "assets": {
        "styles": ["dist/social-polls.css"],
        "scripts": ["dist/social-polls.js"]
    }
}
```

Declared assets:

- use unique relative paths confined to the extension directory;
- are regular text files, never symlinks;
- use `.css` for styles and `.js` or `.mjs` for module scripts;
- are limited to 12 files, 512 KiB per file, and 2 MiB in total;
- are already built and self-contained before deployment;
- cannot use remote URLs in the manifest.

The core does not run npm, Vite, or an extension-provided build command.
Extension authors own dependency bundling and licensing before shipping the
reviewed release.

## Publish a reviewed release

Keep the extension disabled while its source or browser release changes:

```bash
php artisan platform:extensions
php artisan platform:extensions:publish-assets social-polls
```

In production, add Laravel's normal `--force` flag. Publication:

1. preflights every selected extension before the first public write;
2. validates source confinement, text content, type, count, size, and checksum;
3. derives a deterministic release from extension ID/version plus every
   ordered asset path, type, size, and SHA-256 checksum;
4. copies files into an immutable content-addressed directory under
   `public/extensions/`;
5. verifies the copied bytes;
6. atomically writes a private publication receipt under
   `storage/app/private/platform/extensions/assets/`.

Generated public releases and private receipts are deployment state, not public
Git source. Re-running the command for an already verified release is
idempotent.

After migrations and assets are ready, enable the reviewed extension and
rebuild configuration:

```dotenv
LINEWEB_SOCIAL_EXTENSIONS=social-polls
```

```bash
php artisan config:cache
php artisan platform:extensions
```

## Runtime and integrity

Only enabled extensions receive browser tags. Stylesheets and module scripts
use immutable URLs plus SHA-256 Subresource Integrity. Extension release IDs
also participate in Inertia's asset version, so a deploy change forces a full
page reload rather than mixing old and new application state.

Activation fails closed when:

- declared source is missing, linked, binary, oversized, or outside the
  extension;
- the source no longer matches the private publication receipt;
- a published file is missing, changed, or the wrong size;
- the receipt is missing, malformed, or belongs to another release.

Changing a reviewed source file creates a new unpublished release. Disable the
extension, publish that release, and only then enable it again. Existing
immutable releases are retained so open pages and rollback deployments do not
lose referenced files.

## Security boundary

Published JavaScript is **not sandboxed**. It runs as same-origin code with the
same browser authority as the core application and can observe page data and
member interactions. CSS can affect the whole rendered application. Operators
must therefore review the exact built files, their dependency licenses, data
handling, and network behavior before enabling an extension.

Manifest allowlists, hashes, and SRI protect deployment integrity; they do not
make third-party JavaScript safe. Core authorization, policies, validation, and
privacy rules remain server-side and must never depend on an extension UI.

## Deliberate limits

- no browser upload, remote download, package registry, or one-click publish;
- no extension build execution on the application server;
- no JavaScript sandbox, iframe isolation, or Content Security Policy claim;
- no stable JavaScript UI-slot SDK yet; declared slots remain capability
  metadata;
- no automatic deletion or pruning of older immutable releases;
- no persisted browser activation, marketplace update, or destructive
  uninstall workflow.

These limits provide a reproducible deployment primitive without implying that
arbitrary frontend code is isolated or marketplace-ready.
