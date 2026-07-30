# GPS Catering Tracker

## Status

**Delivery pricing.** The repository ships a Laravel 13 baseline, a MySQL
runtime, role-based session authentication (Packet 04), kitchen management
with a Leaflet-based coordinate picker (Packet 05), customer management for
owner and staff with the same map picker and an active/inactive lifecycle
(Packet 06), delivery orders with a five-state lifecycle, `draft ->
scheduled` and `draft/scheduled -> cancelled` transitions, receipt-number
generation, kitchen and customer snapshots captured at scheduling, and a
configurable concurrency cap (default 1, Packet 07), and (as of Packet 08)
a frozen straight-line Haversine `distance_km` and rupiah `fee_rupiah`
captured at scheduling and preserved on cancellation. No tracking, device,
courier, or SMS feature is implemented yet. All remaining functional
components are placeholders pending their specific approved task packets.

## Objective

A catering delivery scheduling and IoT tracking system. Laravel will be the
authoritative calculation and persistence layer for kitchen and delivery
management, endpoint Haversine distance calculation, delivery-fee calculation,
device assignment, tracking-session management, telemetry storage, and customer
tracking. None of these features exist yet.

## Runtime Baseline

- Laravel 13.x
- PHP 8.3.32
- Composer 2.10.2
- Node.js 20.20.2 / npm 10.8.2
- MySQL 8.0.46 for local runtime (database `gps_catering_tracker`)
- SQLite `:memory:` for automated tests
- Blade for the future frontend
- Application timezone: Asia/Jakarta

See `docs/decisions/ADR-001-runtime-baseline.md` for details.

## Development Setup

```bash
cp .env.example .env
php artisan key:generate
# Configure MySQL DB_* variables in .env, then:
php artisan migrate
# Provision the first owner account (interactive, hidden password prompt):
php artisan app:create-owner --name="Owner Name" --email="owner@example.test"
```

The `app:create-owner` command is the ONLY way to create the initial owner
account. The web UI cannot create owners. See
`docs/authentication/initial-owner-command.md`.

`.env.example` still advertises SQLite as the portable default so fresh clones
boot without MySQL. On this VPS, `.env` is configured for MySQL per
`docs/database/mysql-integration.md`.

## Database

- **Runtime:** MySQL 8.0 database `gps_catering_tracker`, accessed by
  `gps_catering_app@localhost` with schema-scoped privileges. See
  `docs/database/mysql-integration.md`.
- **Tests:** SQLite `:memory:` via `phpunit.xml`. No MySQL required to run
  the test suite.

Rationale is captured in `docs/decisions/ADR-006-database-environment-strategy.md`.

## Test Commands

```bash
php artisan test
```

## Asset Build Commands

```bash
npm install
npm run build      # production build
npm run dev        # development / watch
```

## Architectural Directory Overview

```text
app/Domain/Kitchen      Kitchen code normalizer (Packet 05)
app/Domain/Customer     Customer phone normalizer (Packet 06)
app/Domain/Delivery     Delivery state machine, scheduler, canceller,
                        receipt generator, and typed exceptions (Packet 07)
app/Domain/Tracking     Domain concepts for tracking (placeholder)
app/Domain/Device       Domain concepts for devices (placeholder)
app/Application         Use-case orchestration (placeholder)
app/Infrastructure      External integrations (placeholder)
```

Each contains only a `.gitkeep`. Standard Laravel HTTP controllers and
presentation logic remain under the conventional Laravel directories. See
`docs/architecture/project-structure.md`.

## Authentication and Roles

Three account roles exist: `owner`, `staff`, `courier`. The default `web`
guard uses the database session driver. See:

- `docs/authentication/role-access.md` - what each role may do.
- `docs/authentication/initial-owner-command.md` - initial owner setup.
- `docs/decisions/ADR-007-role-based-session-authentication.md` - rationale.
- `docs/requirements/identity-access-requirements.md` - requirement matrix.

## Delivery Orders

Owner and staff can create delivery drafts, schedule them, and cancel them.
Scheduling generates a receipt of the form `DEL-YYYYMMDD-XXXX`, captures
kitchen and customer snapshots atomically, and enforces a configurable
concurrency cap (`DELIVERY_MAX_CONCURRENT_ACTIVE`, default 1). Cancelled
scheduled deliveries preserve their receipt and snapshots. Couriers cannot
access the delivery surface yet. See `docs/deliveries/*` for the operator
workflow, state machine, receipt format, snapshot rules, and concurrency
policy.

## Delivery Distance and Fee

Scheduling now also freezes two values on the delivery row: a
straight-line Haversine `distance_km` (in kilometres, 3 decimals) and a
rupiah `fee_rupiah`. Both use the snapshot coordinates captured at
scheduling, so they cannot drift when the source kitchen or customer is
edited later. Cancellation preserves them. The fee formula is
configurable via `PRICING_MINIMUM_FEE_RUPIAH`,
`PRICING_RATE_PER_KM_RUPIAH`, and `PRICING_FEE_ROUNDING_STEP_RUPIAH`
(defaults `5000`, `2000`, `100`). See
`docs/deliveries/pricing-and-distance.md` and
`docs/decisions/ADR-013-haversine-and-fee-formula.md`.

Not yet implemented: courier assignment, `in_transit` and `delivered`
transitions, tracking, SMS, or IoT features. Do not treat these as
complete.

## Separate Project Warning

`/home/ubuntu/GPS-server` is a **separate, unrelated project**. It is out of
scope and must not be inspected, copied, modified, stopped, or reused by this
project.

## Scope Exclusions

- No payment.
- No food ordering.
- No ETA.
- No road routing.
- No advanced GNSS anti-spoofing.
- No paid map API.
- No multiple active prototype deliveries.

## Contribution Rule

Future implementation must be performed only through approved task packets. No
production feature may be implemented ahead of its designated packet.
