# Extension manifests and activation

Lineweb Social treats extensions as reviewed, deploy-time PHP source. The core
does not download packages or accept ZIP uploads. Opening the administrator
screen never changes executable state.

## Current lifecycle boundary

The executable lifecycle covers discovery, compatibility inspection, explicit
provider activation, database migrations, and immutable browser assets:

1. Deployers place reviewed extension directories under a configured local
   path.
2. Each direct child declares an `extension.json` manifest.
3. The inspector validates the manifest, allowlisted permissions and UI slots,
   duplicate identifiers, and the declared Composer-compatible core constraint.
4. Deployers apply reviewed database migrations and publish reviewed browser
   assets while the extension remains disabled.
5. Deployers add reviewed extension IDs to `LINEWEB_SOCIAL_EXTENSIONS`.
6. The application preflights every enabled manifest, schema, asset release, and
   provider before
   registering any of them.
7. Administrators can review compatibility and active state at
   `/admin/extensions`, but cannot change it there.
8. CI or a release script can run `php artisan platform:extensions`; any
   invalid, duplicated, or incompatible manifest returns a failing exit code.

Inspection never autoloads a disabled provider. A broken manifest is isolated
as its own result so it cannot hide other installed extensions. Enabled
providers are autoloaded only after all enabled IDs pass manifest,
compatibility, uniqueness, class-existence, and Laravel service-provider
checks.

Machine-readable output is available for deployment tooling:

```bash
php artisan platform:extensions --json
```

An installation with no local extensions is valid. Missing configured paths are
ignored so a clean core deployment does not need an empty directory.
The bundled `extensions/` directory is the default. Deployers may set a
platform-native `PATH_SEPARATOR`-delimited list in
`LINEWEB_SOCIAL_EXTENSION_PATHS` when reviewed packages live elsewhere.

## Activate reviewed source

Extension source and its Composer autoload mapping must be installed as part of
the deployment. Inspect it first. When the manifest declares migrations, verify
an external database backup and apply them while the provider is still disabled:

```bash
php artisan platform:extensions
php artisan platform:extensions:migrate example-polls --backup-confirmed
php artisan platform:extensions:publish-assets example-polls
```

Then enable only reviewed manifest IDs:

```dotenv
LINEWEB_SOCIAL_EXTENSIONS=example-polls,another-reviewed-extension
```

After changing the environment, rebuild Laravel's configuration cache and run
the inspection command before switching traffic:

```bash
php artisan config:cache
php artisan platform:extensions
```

An unknown ID, incompatible or duplicate manifest, missing provider class, or a
class that does not extend Laravel's `ServiceProvider` fails application
startup. Provider registration and boot exceptions also fail startup; arbitrary
PHP cannot be sandboxed or rolled back safely inside the running process.

The reference `example-polls` manifest demonstrates the declaration format. It
is intentionally inactive by default and does not ship a functional provider.

## Manifest contract

The reference manifest lives at
[`extensions/example-polls/extension.json`](../extensions/example-polls/extension.json).

Required fields:

| Field         | Contract                                                                                          |
| ------------- | ------------------------------------------------------------------------------------------------- |
| `id`          | Unique lowercase kebab-case identifier, up to 80 characters.                                      |
| `name`        | Human-readable name, up to 120 characters.                                                        |
| `version`     | Semantic extension version.                                                                       |
| `license`     | Declared source license.                                                                          |
| `authors`     | Non-empty author list; optional links must use HTTPS.                                             |
| `core`        | Composer version constraint for compatible Lineweb Social releases.                               |
| `provider`    | Fully qualified PHP service-provider class declaration.                                           |
| `permissions` | Unique values from the core permission allowlist.                                                 |
| `ui_slots`    | Unique values from the core presentation-slot allowlist.                                          |
| `database`    | Optional confined migration directory plus the required first-contract `retain` uninstall policy. |
| `assets`      | Optional lists of confined, pre-built CSS and ES-module files for immutable deployment publication. |

The current core version and allowlists live in `config/extensions.php`.
Deployers may set `LINEWEB_SOCIAL_CORE_VERSION` for a deliberately versioned
downstream build, but it must remain a valid semantic version.

Permissions and UI slots are declarations, not a security sandbox. Core
policies, middleware, validation, and visibility checks still own every
protected operation.

## Deliberately unavailable

The following are not implemented and must not be implied by an extension
listing:

- web upload, remote package discovery, or one-click installation;
- activation or deactivation from the web;
- persisted activation state, upgrade, or uninstall workflows;
- marketplace signing, review, billing, or update delivery;
- runtime isolation for arbitrary PHP code.

Deploy-time provider registration is fail-fast, not runtime isolation. Reviewed
database migrations now have an ownership/checksum registry plus explicit,
backup-gated migrate and latest-batch rollback commands; the full limits are in
[`extension-migrations.md`](extension-migrations.md). Reviewed browser assets
use immutable checksummed releases and SRI as documented in
[`extension-assets.md`](extension-assets.md). A stable JavaScript slot SDK and
destructive uninstall still require separate contracts before the project can
call the extension foundation a complete marketplace plugin system.
