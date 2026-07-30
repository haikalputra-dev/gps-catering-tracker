# ADR-006: Database Environment Strategy

- **Status:** Accepted
- **Date:** 2026-07-30
- **Deciders:** Project owner (haikalputra-dev)
- **Related:** ADR-001 (Runtime Baseline), Packet 03 (MySQL Integration)

## Context

The bootstrap in Packet 02 used SQLite as a temporary local store so the
Laravel skeleton could boot without external infrastructure. The application
must now move to a persistent MySQL runtime for local development on the VPS,
while automated tests must remain fast, isolated, and independent of the
MySQL service.

Constraints to respect:

- Runtime is a single Ubuntu 24.04 VPS with MySQL 8.0 already installed,
  active, and bound to `127.0.0.1` (see `docs/environment-audit.md`).
- No production environment exists; the VPS itself is the development host.
- Tests run inside CI-style loops locally (`php artisan test`) and should
  not require credentials, a running MySQL, or teardown of shared state.
- Credentials must never enter version control.
- A separate Laravel project (`/home/ubuntu/GPS-server`) exists on the same
  host and must remain untouched. Any MySQL objects created for this project
  must be namespaced and privilege-isolated.

## Decision

Adopt a two-connection strategy:

1. **Runtime (local development on the VPS): MySQL 8.0**
   - Database: `gps_catering_tracker`
   - Application user: `gps_catering_app@localhost`
   - Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`
   - Host binding remains `127.0.0.1` only (unchanged from Packet 01).
   - Application connects over the local UNIX/TCP loopback using
     credentials sourced from `.env`.
2. **Automated tests: SQLite `:memory:`**
   - Configured in `phpunit.xml` via `DB_CONNECTION=sqlite` and
     `DB_DATABASE=:memory:`.
   - No dependency on MySQL, no shared state between test runs, no
     credentials required.

The two connections are not expected to diverge behaviourally at this stage
because the schema is still trivial (framework baseline only). Divergence
will be revisited before the first non-trivial migration that uses a
MySQL-specific feature (see Consequences).

## Alternatives Considered

- **MySQL for both runtime and tests.** Rejected: forces every contributor
  and CI pipeline to provision a MySQL instance and a dedicated test schema,
  slows the test loop, and introduces credential handling into test config.
- **SQLite for both runtime and tests.** Rejected: does not match the
  production-shaped target (MySQL is the deployment substrate on this host),
  and would hide MySQL-specific issues until much later.
- **Docker-based MySQL for tests via testcontainers.** Rejected for the
  prototype phase: adds container lifecycle management, increases test
  latency, and offers no benefit while the schema stays framework-baseline.
  Revisit if/when MySQL-specific behaviour must be exercised in tests.

## Consequences

Positive:

- Fast test loop (in-memory SQLite, no I/O, no external service).
- Runtime matches the deployment substrate, surfacing MySQL-specific issues
  during ordinary development.
- Least-privilege isolation: the application user cannot touch any schema
  other than `gps_catering_tracker`.
- No production risk introduced; VPS remains a single-host dev environment.

Negative / risks:

- Behavioural drift between SQLite and MySQL is possible once migrations
  start using MySQL-specific types, JSON columns, generated columns,
  full-text indexes, or foreign-key semantics that SQLite emulates loosely.
- Feature tests that assert database-level constraints may pass on SQLite
  and fail on MySQL.

Mitigations:

- Track this as an active risk in `docs/project/risk-register.md`.
- Before merging any migration that uses a MySQL-specific feature,
  re-evaluate whether tests should run against MySQL for that suite.
- Keep migrations portable (avoid raw MySQL DDL) unless explicitly needed.

## Follow-ups

- Revisit test-database strategy when the first domain migration lands.
- Document a credential-rotation procedure before the app leaves the
  prototype phase.
