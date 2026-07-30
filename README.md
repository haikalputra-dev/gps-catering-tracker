# GPS Catering Tracker

## Status

**Initialization only.** This repository contains a freshly initialized Laravel
13 baseline. No business feature has been implemented yet. All functional
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
- SQLite for local development and automated tests
- MySQL 8.0.46 for later deployment (configured in a later packet)
- Blade for the future frontend
- Application timezone: Asia/Jakarta

See `docs/decisions/ADR-001-runtime-baseline.md` for details.

## Development Setup

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

## SQLite Usage

Local development and tests use SQLite. The database file lives at:

```text
database/database.sqlite
```

`DB_CONNECTION=sqlite` is set in `.env`. MySQL integration is deferred to a
later packet.

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
app/Domain/Kitchen      Domain concepts for kitchens (placeholder)
app/Domain/Delivery     Domain concepts for deliveries (placeholder)
app/Domain/Tracking     Domain concepts for tracking (placeholder)
app/Domain/Device       Domain concepts for devices (placeholder)
app/Application         Use-case orchestration (placeholder)
app/Infrastructure      External integrations (placeholder)
```

Each contains only a `.gitkeep`. Standard Laravel HTTP controllers and
presentation logic remain under the conventional Laravel directories. See
`docs/architecture/project-structure.md`.

## No Business Feature Implemented

This baseline implements **no** kitchen, delivery, user-role, tracking,
Haversine, pricing, SMS, or IoT feature. Do not treat any component as complete.

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
