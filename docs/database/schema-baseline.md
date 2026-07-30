# Schema Baseline

Snapshot of the `gps_catering_tracker` database contents immediately after
the Packet 03 MySQL integration. This is the framework-baseline schema
only. No domain tables exist yet.

## Purpose

Provide a checkpoint that future migration reviews can diff against, so it
stays obvious which tables were introduced by domain work versus which
were carried over from Laravel's framework migrations.

## Tables

| Table                     | Origin migration                          | Purpose                                              |
|---------------------------|-------------------------------------------|------------------------------------------------------|
| `migrations`              | Laravel core                              | Migration version ledger                             |
| `users`                   | `0001_01_01_000000_create_users_table`    | Framework users table (unused by domain yet)         |
| `password_reset_tokens`   | `0001_01_01_000000_create_users_table`    | Framework password reset support                     |
| `sessions`                | `0001_01_01_000000_create_users_table`    | Framework session store (used when driver=database)  |
| `cache`                   | `0001_01_01_000001_create_cache_table`    | Framework cache store (used when driver=database)    |
| `cache_locks`             | `0001_01_01_000001_create_cache_table`    | Framework atomic-lock support for cache              |
| `jobs`                    | `0001_01_01_000002_create_jobs_table`     | Queue driver=database backing table                  |
| `job_batches`             | `0001_01_01_000002_create_jobs_table`     | Queue batching metadata                              |
| `failed_jobs`             | `0001_01_01_000002_create_jobs_table`     | Queue failure log                                    |

Domain-specific tables for kitchen, delivery, tracking, and device
concerns are intentionally absent. They will land in later packets under
`app/Domain/*` with dedicated migrations.

## Database-level metadata

- **Charset:** `utf8mb4`
- **Collation:** `utf8mb4_unicode_ci`
- **Engine (default):** InnoDB (MySQL 8.0 default)
- **Row format (default):** DYNAMIC (MySQL 8.0 default)

All application tables inherit the database-level charset and collation
unless a migration overrides them.

## Framework tables that are currently unused

Even though these tables exist, the application does not depend on them
at the current stage:

- `users`, `password_reset_tokens` — authentication is not implemented yet.
- `sessions` — session driver is currently `array` (in-memory).
- `cache`, `cache_locks` — cache driver is currently `database` per
  Laravel default, but no cache traffic is generated yet.
- `jobs`, `job_batches`, `failed_jobs` — queue driver defaults to `sync`,
  so nothing is written yet.

They are retained rather than dropped so that toggling drivers to
`database` for any of the above continues to work out of the box.

## How to reproduce this baseline

From a clean MySQL database, after `.env` is configured per
`docs/database/mysql-integration.md`:

```
php artisan migrate --force
```

The `--force` flag is required because `APP_ENV=local` and the migration
command declines destructive runs by default in non-production envs.

## Verification queries

```
SHOW TABLES IN gps_catering_tracker;
SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
  FROM INFORMATION_SCHEMA.SCHEMATA
 WHERE SCHEMA_NAME = 'gps_catering_tracker';
```

Expected: nine tables listed above, `utf8mb4` / `utf8mb4_unicode_ci`.

## Next expected mutations

- First domain migration (kitchen or delivery bounded context) will
  extend this baseline. When it lands, this document should be updated
  in the same commit so it stays authoritative.
