# API v1 contract

## Status

This document defines the first public contract for authenticated native and
decoupled clients. `GET /api/v1/me` is the first available operation; the other
endpoints in `openapi.json` remain **planned, not yet implemented**.
Contract-first development keeps client expectations separate from the current
Inertia view models and prevents accidental exposure of raw database records.

The first implementation slice is deliberately read-only. Write operations,
interactive native login, third-party OAuth, webhooks, and realtime delivery
need separate reviews before they enter the contract.

## Compatibility boundary

- Every endpoint is rooted at `/api/v1`.
- Removing a field, changing its type, tightening an enum, or changing ordering
  is a breaking change and requires a new API version.
- Adding an optional field is allowed within v1. Clients must ignore fields they
  do not understand.
- Identifiers are serialized as strings even when the current database uses
  integers. Clients must treat cursors and identifiers as opaque values.
- API field names use `snake_case`. Existing Inertia page props are an internal
  web contract and may continue to use `camelCase`.
- All timestamps are UTC ISO 8601 strings.

The canonical machine-readable draft is [`openapi.json`](openapi.json). The
document uses OpenAPI 3.1 so it can be validated with an ordinary JSON parser
without adding a runtime documentation dependency.

## Authentication and token lifecycle

The existing first-party Inertia application continues to use Fortify session
authentication, verified email, passkeys, two-factor authentication, CSRF
protection, and the current login throttles.

API v1 uses Laravel Sanctum bearer tokens for native, automation, and decoupled
clients. The API does not accept an email and password at a token endpoint.
Tokens are created from the verified Security settings screen after recent
password confirmation. This avoids creating a second login path that bypasses
Fortify's passkey or two-factor challenges.

Token rules:

- store only Sanctum's token digest and show the plaintext value once;
- require a human-readable device or integration name;
- expire after 30 days by default and never exceed 90 days;
- allow at most 10 active tokens per account;
- provide per-token and revoke-all controls in account security settings;
- revoke all tokens after account deletion or password-reset recovery;
- prune expired records on the scheduler;
- never issue wildcard (`*`) abilities to user-created tokens.

Interactive native authentication remains a later decision. If third-party
public clients are supported, use an authorization flow designed for public
clients, such as Authorization Code with PKCE, rather than collecting the
member's password inside another application.

## Token abilities

The read-only slice recognizes these abilities:

| Ability | Access |
| --- | --- |
| `profile:read` | The authenticated member's own safe profile. |
| `profiles:read` | Profiles already visible to the member. |
| `spaces:read` | Discoverable Spaces and viewer membership state. |
| `feed:read` | Policy-filtered feed, posts, comments, and private post media. |
| `notifications:read` | The member's policy-resolved notification history. |

Abilities are an additional boundary, not authorization by themselves. Every
request must still pass the same policies, visibility scopes, membership rules,
mute/block boundaries, and moderation state as the web application.

Future write abilities are reserved but are not granted or accepted by the
first slice: `posts:write`, `comments:write`, `memberships:write`,
`relationships:write`, and `notifications:write`.

## Response shape

Single resources use:

```json
{
  "data": {}
}
```

Collections use opaque cursor pagination:

```json
{
  "data": [],
  "links": {
    "next": "/api/v1/feed?cursor=opaque-value&limit=20"
  },
  "meta": {
    "next_cursor": "opaque-value",
    "has_more": true,
    "limit": 20
  }
}
```

Clients must follow `links.next` or return `meta.next_cursor` unchanged. They
must not decode cursors or construct one from an identifier or timestamp. Feed,
notifications, and profile activity use `(published_at DESC, id DESC)` or the
equivalent creation timestamp. Conversation comments use
`(published_at ASC, id ASC)`. The identifier is always the deterministic
tie-breaker.

Validation and other errors use one stable envelope:

```json
{
  "message": "The request could not be completed.",
  "code": "validation_failed",
  "request_id": "019f6f09-7f6b-7f4d-a277-8956332f6fb7",
  "errors": {
    "limit": ["The limit must be between 1 and 50."]
  }
}
```

`errors` is present only for field-level validation. Production errors never
contain exception messages, stack traces, SQL, filesystem paths, internal model
names, or policy details. Responses include the same opaque `X-Request-ID` in
the header for support correlation.

Standard status codes are `200`, `400`, `401`, `403`, `404`, `422`, and `429`.
The API uses `404` instead of confirming that inaccessible private content
exists when that distinction would leak information.

## Pagination and limits

- Collection limits default to 20 and are capped at 50.
- Cursors are scoped to the authenticated member, filters, ordering, and API
  version. Reusing one with different filters returns `400 invalid_cursor`.
- Offset pagination is not part of API v1.
- Feed and notification queries must apply visibility before pagination so
  filtering cannot produce cross-page leaks or unstable counts.
- Total counts are omitted from cursor collections unless they are already
  cheap, policy-safe, and necessary to the product surface.

## Throttling

The first implementation will apply named Laravel limiters using the
authenticated member and current token identifier, with IP fallback before
authentication:

| Class | Initial ceiling |
| --- | --- |
| Normal JSON reads | 120 requests per minute |
| Discovery/search-style reads | 30 requests per minute |
| Private media delivery | 120 requests per minute plus infrastructure bandwidth limits |
| Future writes | Separate endpoint-specific limiters, never the read bucket |

Successful responses expose Laravel's `X-RateLimit-Limit` and
`X-RateLimit-Remaining` headers. A `429` response also includes
`X-RateLimit-Reset` and `Retry-After`. Limits are deployment defaults, not a
promise that every installation has identical capacity.

## CORS and transport

- Production API traffic requires HTTPS.
- Browser origins are an explicit `API_ALLOWED_ORIGINS` environment allowlist;
  an empty value denies cross-origin browser access and `*` is not a valid
  production origin.
- Bearer-token API routes do not enable credentialed cross-origin cookies.
- The existing same-origin Inertia session remains on the web middleware stack
  and is not converted into a cross-origin API session.
- Native clients are not governed by browser CORS, but receive no weaker auth,
  rate-limit, or policy treatment.
- Authentication tokens, cursor values, and private media URLs must not appear
  in application logs, analytics events, or referrer-bearing public links.

## Policy-safe serialization

Controllers must return explicit Laravel API Resources or equally strict typed
projection objects. Returning Eloquent models, database notifications, or
`toArray()` output directly is forbidden.

The first resources expose only allowlisted fields:

- profiles exclude email, verification state, privacy configuration, login
  metadata, relationship rows, and undiscoverable membership;
- Spaces exclude invitation recipients, audit records, and hidden membership;
- posts and comments exclude storage paths, report details, moderator notes,
  hidden timestamps, and author account identifiers;
- media exposes only the authorized API URL, alt text, normalized dimensions,
  and MIME type;
- notifications are re-resolved at read time and expose a safe structured
  target or `null`, never their stored internal payload.

The API must not trust a resource serialized earlier. It rechecks access on
every request, including private media delivery and notification destinations.
Blocked or muted actors, hidden content, revoked membership, and deleted targets
must disappear according to the existing core policies.

Private media remains bearer-authenticated in v1. A decoupled browser client
must fetch the image with its API client and render a local object URL; placing
the API URL directly in an `<img>` element will not attach the bearer token.
Public or long-lived signed media URLs are intentionally outside this draft.

## Read-only endpoint slice

The read-only API roadmap contains:

- `GET /api/v1/me` — available
- `GET /api/v1/profiles/{handle}`
- `GET /api/v1/spaces`
- `GET /api/v1/spaces/{slug}`
- `GET /api/v1/feed`
- `GET /api/v1/posts/{post}`
- `GET /api/v1/posts/{post}/comments`
- `GET /api/v1/posts/{post}/media`
- `GET /api/v1/notifications`

OpenAPI operations carry `x-lineweb-status: planned` until their routes,
resources, authorization, throttling, and feature tests exist. Only `/me`
currently carries `x-lineweb-status: available`. Documentation must never make
a planned endpoint look available.

## Implementation acceptance gates

Before changing an operation to `available`:

1. The route requires Sanctum authentication, verified email, and its declared
   token ability.
2. A dedicated Resource or typed projection allowlists every response field.
3. Feature tests cover public, private, hidden, non-member, mute, block,
   moderation, deleted-target, and expired/revoked-token boundaries where they
   apply.
4. Cursor tests cover deterministic ordering, invalid reuse, maximum limits,
   and no duplicate or missing records across pages.
5. Throttle tests assert the JSON error envelope and retry headers.
6. The OpenAPI example is generated from or checked against the actual response
   shape.
7. CI, dependency audits, and the public source hygiene check pass.
