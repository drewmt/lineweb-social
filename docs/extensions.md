# Extension manifests and compatibility

Lineweb Social treats extensions as reviewed, deploy-time PHP source. The core
does not download packages, accept ZIP uploads, or execute a service provider
because an administrator opened a web page.

## Current lifecycle boundary

The initial lifecycle covers discovery and compatibility inspection:

1. Deployers place reviewed extension directories under a configured local
   path.
2. Each direct child declares an `extension.json` manifest.
3. The inspector validates the manifest, allowlisted permissions and UI slots,
   duplicate identifiers, and the declared Composer-compatible core constraint.
4. Administrators can review the same results at `/admin/extensions`.
5. CI or a release script can run `php artisan platform:extensions`; any
   invalid, duplicated, or incompatible manifest returns a failing exit code.

Inspection never autoloads the declared provider. A broken manifest is isolated
as its own result so it cannot hide other installed extensions.

Machine-readable output is available for deployment tooling:

```bash
php artisan platform:extensions --json
```

An installation with no local extensions is valid. Missing configured paths are
ignored so a clean core deployment does not need an empty directory.

## Manifest contract

The reference manifest lives at
[`extensions/example-polls/extension.json`](../extensions/example-polls/extension.json).

Required fields:

| Field | Contract |
| --- | --- |
| `id` | Unique lowercase kebab-case identifier, up to 80 characters. |
| `name` | Human-readable name, up to 120 characters. |
| `version` | Semantic extension version. |
| `license` | Declared source license. |
| `authors` | Non-empty author list; optional links must use HTTPS. |
| `core` | Composer version constraint for compatible Lineweb Social releases. |
| `provider` | Fully qualified PHP service-provider class declaration. |
| `permissions` | Unique values from the core permission allowlist. |
| `ui_slots` | Unique values from the core presentation-slot allowlist. |

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
- automatic service-provider registration;
- extension migration execution or rollback;
- extension JavaScript or stylesheet loading;
- activation, deactivation, upgrade, or uninstall state;
- marketplace signing, review, billing, or update delivery;
- runtime isolation for arbitrary PHP code.

These require explicit failure handling, rollback, data-ownership, and upgrade
contracts before the project can call the extension foundation a plugin system.
