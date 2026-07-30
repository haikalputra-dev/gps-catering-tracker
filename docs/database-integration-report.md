# Database Integration Report (Packet 03)

- **Date:** 2026-07-30
- **Packet:** 03 - MySQL Runtime Integration
- **Baseline commit before packet:** `f39e10f` (docs: add Packet 02 bootstrap report)
- **Predecessor packet commit:** `760c8c5` (chore: initialize Laravel project baseline)
- **Host:** Ubuntu 24.04.4 LTS VPS, MySQL 8.0.46
- **Scope:** Provision an isolated MySQL database and least-privilege user
  for the Laravel runtime; keep the test suite on in-memory SQLite; add
  supporting documentation. No business logic introduced.

## Objective

Move the Laravel runtime off SQLite and onto a dedicated MySQL database
that lives on the same VPS, with credentials that are strictly scoped to
this project. Preserve the fast, hermetic test loop by keeping PHPUnit on
SQLite `:memory:`. Do not change any host-level MySQL configuration.

## Outcome

- MySQL runtime is live, authenticated, and passing baseline migrations.
- Test suite still uses SQLite in-memory and continues to pass.
- Documentation and one new ADR capture the decision and procedure.
- No secrets committed. Working tree remains under the guard rails from
  Packets 01 and 02.

## What Changed

### Database objects (host side)

- Created database `gps_catering_tracker` (`utf8mb4` /
  `utf8mb4_unicode_ci`).
- Created user `gps_catering_app@localhost` with a randomly generated
  48-hex-character password.
- Granted `ALL PRIVILEGES ON gps_catering_tracker.*` to that user. No
  global privileges. No `GRANT OPTION`.

### Application configuration

- `.env`: switched `DB_CONNECTION=sqlite` to `DB_CONNECTION=mysql`,
  populated `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
  `DB_PASSWORD` for the new user. `.env` remains git-ignored.
- `.env.example`: unchanged (portable SQLite defaults retained so fresh
  clones still boot without MySQL).
- `phpunit.xml`: unchanged. It already declared
  `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`.

### Migrations

- Ran `php artisan migrate --force` against MySQL. All three framework
  baseline migrations succeeded, producing the nine tables catalogued in
  `docs/database/schema-baseline.md`.

### Documentation

- Added `docs/decisions/ADR-006-database-environment-strategy.md`.
- Added `docs/database/mysql-integration.md`.
- Added `docs/database/schema-baseline.md`.
- Updated `docs/project/decision-log.md`, `docs/project/risk-register.md`,
  `docs/project/progress.md`.
- Updated `README.md` runtime section.

### Nothing else changed

- No composer/npm dependencies added.
- No PHP source files added or edited.
- No host services restarted or reconfigured.
- No firewall or bind-address changes.
- `/home/ubuntu/GPS-server` untouched.

## Verification Evidence

### MySQL service state

```
$ systemctl is-active mysql
active
$ ss -lnt | grep :3306
LISTEN 0 151  127.0.0.1:3306   0.0.0.0:*
LISTEN 0 70   127.0.0.1:33060  0.0.0.0:*
```

Loopback-only binding preserved.

### Grants

```
SHOW GRANTS FOR 'gps_catering_app'@'localhost';
```

```
GRANT USAGE ON *.* TO `gps_catering_app`@`localhost`
GRANT ALL PRIVILEGES ON `gps_catering_tracker`.* TO `gps_catering_app`@`localhost`
```

`USAGE` is MySQL's null privilege placeholder, not global access.

### Charset / collation

```
SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
  FROM INFORMATION_SCHEMA.SCHEMATA
 WHERE SCHEMA_NAME='gps_catering_tracker';
-- utf8mb4  utf8mb4_unicode_ci
```

### Laravel authentication smoke test

```
$ php artisan config:clear
INFO Configuration cache cleared successfully.
$ php artisan tinker --execute="echo DB::connection()->getPdo() ? 'OK' : 'FAIL'; echo PHP_EOL; echo DB::connection()->getDatabaseName();"
MySQL connect OK
DB: gps_catering_tracker
```

### Migration output

```
$ php artisan migrate --force
INFO Preparing database.
Creating migration table .................. 46.34ms DONE
INFO Running migrations.
0001_01_01_000000_create_users_table ..... 212.96ms DONE
0001_01_01_000001_create_cache_table ..... 139.40ms DONE
0001_01_01_000002_create_jobs_table ...... 225.76ms DONE
```

### Test suite (SQLite in-memory)

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":2,"passed":2,"assertions":2,"duration_ms":116}
```

### Quality checks

- `composer validate --strict` -> `./composer.json is valid`
- `composer audit` -> `No security vulnerability advisories found.`
- `npm audit` -> `found 0 vulnerabilities`
- `git diff --check` -> clean (no whitespace/conflict markers)

## Security Notes

- Password generation used `php -r 'echo bin2hex(random_bytes(24));'`
  (48 hex chars, 192 bits of entropy).
- The SQL provisioning file was created via `mktemp` with `umask 077` and
  explicit `chmod 600`, applied via `sudo mysql`, then deleted.
- The password was never printed to stdout, logged, or written to
  `~/.bash_history`. It exists only inside `.env` (mode 600 by Laravel
  convention) on disk.
- `.env.packet03-backup` was created as a safety net during the switch
  and is removed as part of packet completion. `.env*` remains covered by
  the framework's default `.gitignore`.
- Application user has no `GRANT OPTION`, no global privileges, and no
  visibility into other schemas on the host.

## Files Touched

Added:

- `docs/decisions/ADR-006-database-environment-strategy.md`
- `docs/database/mysql-integration.md`
- `docs/database/schema-baseline.md`
- `docs/database-integration-report.md`

Modified:

- `.env` (git-ignored; runtime credentials switched to MySQL)
- `docs/project/decision-log.md`
- `docs/project/risk-register.md`
- `docs/project/progress.md`
- `README.md`

Not modified (intentionally):

- `.env.example`
- `phpunit.xml`
- `config/*.php`
- Any file under `app/`, `routes/`, `resources/`, `database/migrations/`

## Deviations From The Packet

None. All acceptance criteria were met without changes to host services,
firewall state, or the neighbouring `GPS-server` project.

## Next Packet Prerequisites

- Runtime database is ready to receive domain migrations.
- Test strategy for domain migrations may need revisiting before the
  first migration that relies on MySQL-specific features (tracked in
  ADR-006 and the risk register).
- No further host provisioning is required for the next packet.
