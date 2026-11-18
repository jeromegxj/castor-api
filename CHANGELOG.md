# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - Unreleased

First public release.

### Added

- `#[AsApi]` attribute: tasks marked with both `#[AsTask]` and `#[AsApi]` are discovered at Castor boot.
- `castor api:export-openapi`: writes the OpenAPI contract to `.castor/api/openapi.json`.
- HTTP front controller (`public/index.php`) serving `POST /tasks/{task}/run` for synchronous runs.
- Async runs (`async: true`) with `/start` and `/status/{runId}` endpoints, run by background workers.
- Locks on conflicting runs, and an endpoint listing the active runs.
- `castor api:serve-development` to serve the API locally.
- Bearer-token authentication through `CASTOR_API_TOKEN`.

### Fixed

- Options are never marked as required in the task schema.
- Installation with Castor 1.7+.

[Unreleased]: https://github.com/jeromegxj/castor-api/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/jeromegxj/castor-api/releases/tag/v0.1.0
