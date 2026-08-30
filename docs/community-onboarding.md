# Community onboarding

Lineweb Social gives newly verified members a short, optional route into a real
community. The guide focuses on four observable outcomes: a recognizable
profile, at least one Space membership, one chosen follow, and one published
post.

## Entry and exit behavior

- A verified, active member with no Space membership is sent from `/dashboard`
  to `/getting-started`.
- A pending shareable Space invitation always takes priority over the guide.
- Direct navigation to the feed remains available at all times.
- A member may hide the guide. This stores only the dismissal timestamp and
  does not create content, alter privacy settings, or join or follow anything.
- The guide remains available from the account menu after dismissal.

## Recommendations and privacy

Space suggestions use the existing discovery boundary and exclude Spaces the
member has already joined. This means the result can contain public Spaces only;
private and hidden Spaces are never revealed through onboarding.

People suggestions reuse the existing discoverability, profile visibility, and
mutual block scopes. They exclude the current member and profiles already
followed. The guide exposes only the person's name, handle, optional headline,
and an aggregate shared Space count.

No recommendation score, behavioral profile, view event, or onboarding
analytics record is created. Joining, following, profile editing, and posting
continue through their existing authorized and rate-limited actions.

## Data rights

`users.onboarding_dismissed_at` is nullable and records only the member's choice
to hide the guide. It is included in the member's personal JSON export and is
deleted with the account.

## Extension boundary

The onboarding projection is currently a core web contract. Extensions should
not mutate its steps or recommendations at runtime. A future extension point
must define ordering, authorization, privacy, and completion semantics before
third-party steps can be registered safely.
