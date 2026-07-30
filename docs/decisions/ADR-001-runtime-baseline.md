# ADR-001 — Runtime Baseline

## Status

Accepted

## Context

The GPS Catering Tracker project runs on an existing Amazon VPS whose
environment was audited in `docs/environment-audit.md`. The approved provisional
stack targeted PHP 8.5 and MySQL 8.4 LTS, but the host currently provides PHP
8.3.32 and MySQL 8.0.46. No PHP or MySQL upgrade is currently approved.

## Decision

The runtime baseline for the current phase is:

- Laravel 13.x (framework).
- PHP 8.3.32 (existing host version).
- MySQL 8.0.46 for later deployment integration.
- SQLite for initial development and automated tests.
- Composer 2.10.2.
- Node.js 20.20.2 with npm 10.8.2.

No PHP or MySQL upgrade is currently approved. Laravel 13 runs correctly on PHP
8.3, so the version targets in the original proposal are deferred, not blocking.

## Consequences

- Development and CI-style tests use SQLite, avoiding a MySQL dependency early.
- MySQL integration will be configured in a later packet against the existing
  8.0.46 server unless an upgrade is separately approved.
- Any need for PHP 8.5-only features would require a separate, approved system
  change.
