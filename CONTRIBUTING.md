# Contributing

Thank you for helping build Lineweb Social and a healthier self-hosted social web.

## Before opening a change

- Search existing issues and pull requests first.
- Keep each change focused on one user or maintainer problem.
- Discuss new product areas and extension-contract changes before implementing them.
- Do not add remote code loading, hidden telemetry, advertising hooks, or essential features locked behind an upsell.

## Development expectations

- Enforce permissions and visibility on the server before querying or caching data.
- Validate all untrusted input and escape output through framework-safe rendering.
- Add or update tests for behavior and authorization boundaries.
- Keep the default experience accessible, responsive, and usable without an algorithmic feed.
- Avoid unnecessary dependencies and document any new runtime dependency.

Run the complete checks documented in `README.md` before opening a pull request. Describe the human reason for the change, its impact, and exactly how it was tested.

## Communication

Be brief, constructive, and kind. Critique code and decisions rather than people. Harassment, discrimination, and spam are not welcome.

Participation in this project is governed by our
[`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).
