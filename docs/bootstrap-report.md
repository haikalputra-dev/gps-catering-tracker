# Bootstrap Report — GPS Catering Tracker

Task Packet 02: Initialize Laravel Project Baseline.

## 1. Execution Date and Timezone

- Execution date: 2026-07-30
- Report timezone: Asia/Jakarta (WIB, UTC+7); host system timezone is UTC.

## 2. Laravel Version

- Laravel Framework 13.23.0 (`php artisan --version`)
- `composer show laravel/framework` → v13.23.0

## 3. PHP Version

- PHP 8.3.32 (cli, NTS)

## 4. Composer Version

- Composer 2.10.2 (2026-07-01)

## 5. Node.js and npm Versions

- Node.js v20.20.2
- npm 10.8.2

## 6. Database Mode Used

- SQLite (development and automated tests). `DB_CONNECTION=sqlite`.
- MySQL integration deferred to a later packet.

## 7. SQLite File Location

- `database/database.sqlite` (inside the project directory).

## 8. Application Timezone

- `Asia/Jakarta` (set via `APP_TIMEZONE` in `.env`; `config/app.php` now reads
  `env('APP_TIMEZONE', 'UTC')`). Confirmed via `php artisan about` → Timezone:
  Asia/Jakarta.

## 9. Composer Installation Result

- `composer create-project laravel/laravel ^13.0` succeeded.
- 79 packages installed; no security vulnerability advisories reported during
  creation.
- `composer validate --strict` → `./composer.json is valid`.

## 10. npm Installation Result

- `npm install` → added 64 packages, audited 65, found 0 vulnerabilities.
- No global npm packages were installed. Leaflet was NOT installed.

## 11. Migration Result

- Migrations ran during project creation against SQLite (users, cache, jobs
  tables created successfully).
- `php artisan migrate --no-interaction` → `INFO Nothing to migrate.` (schema
  already applied). SQLite file present and populated.

## 12. Test Result

- `php artisan test` → PASSED. 2 tests, 2 assertions, ~130ms.

## 13. Asset-Build Result

- `npm run build` (vite v8.1.5) → built successfully in ~385ms.
- Output written to `public/build/` (manifest, fonts, app CSS/JS).
- Non-blocking note: Vite emitted an optional-feature notice about the
  `fontaine` package for optimized font fallbacks. Not installed (optional).

## 14. Composer Audit Result

- `composer audit` → `No security vulnerability advisories found.`

## 15. npm Audit Result

- `npm audit` → `found 0 vulnerabilities.`
- No packages were updated in response to audit (none required).

## 16. Local HTTP Boot-Check Result

- Started `php artisan serve --host=127.0.0.1 --port=8000` (loopback only).
- `curl -I http://127.0.0.1:8000` → `HTTP/1.1 200 OK` (X-Powered-By: PHP/8.3.32).
- The server was bound to 127.0.0.1 only, NOT 0.0.0.0.
- The development server was stopped after verification; port 8000 is closed and
  no permanent process/service was created.

## 17. Git Initialization Result

- `git init -b main` → initialized repository on branch `main`.
- Git identity was already configured (name and email present), so an initial
  commit was created.
- No remote was configured.

## 18. Initial Commit Hash

- Full: 760c8c56288fcb3f0a180803de58770e04d1609a
- Short: 760c8c5
- Message: `chore: initialize Laravel project baseline`

## 19. Git Status

- On branch `main`; working tree clean.
- No remote configured.

## 20. Files Created Outside Standard Laravel Files

```text
app/Application/.gitkeep
app/Domain/Delivery/.gitkeep
app/Domain/Device/.gitkeep
app/Domain/Kitchen/.gitkeep
app/Domain/Tracking/.gitkeep
app/Infrastructure/.gitkeep

docs/environment-audit.md            (from Task Packet 01, preserved)
docs/bootstrap-report.md             (this report)
docs/architecture/system-context.md
docs/architecture/project-structure.md
docs/decisions/ADR-001-runtime-baseline.md
docs/decisions/ADR-002-pricing-distance-authority.md
docs/decisions/ADR-003-free-mapping-stack.md
docs/decisions/ADR-004-customer-tracking-authentication.md
docs/decisions/ADR-005-prototype-concurrency.md
docs/project/decision-log.md
docs/project/risk-register.md
docs/project/progress.md
```

## 21. Standard Laravel Files Intentionally Modified

- `.env` — configured APP_NAME, APP_URL, APP_TIMEZONE=Asia/Jakarta,
  DB_CONNECTION=sqlite (secrets not shown). Not tracked by Git (gitignored).
- `.env.example` — set portable defaults (APP_NAME, APP_ENV, APP_DEBUG, APP_URL,
  APP_TIMEZONE, DB_CONNECTION=sqlite); no absolute paths or secrets.
- `config/app.php` — timezone entry changed from hard-coded `'UTC'` to
  `env('APP_TIMEZONE', 'UTC')`. Locale untouched.
- `README.md` — replaced the default Laravel README with project-specific
  content.

## 22. Confirmation: No Business Feature Implemented

No kitchen, delivery, user-role, tracking, Haversine, pricing, SMS, or IoT
feature was implemented. The domain/application/infrastructure directories
contain only `.gitkeep` placeholders. No models, controllers, services,
repositories, DTOs, enums, custom migrations, middleware, API routes, or
business logic were added.

## 23. Confirmation: /home/ubuntu/GPS-server Untouched

The separate `/home/ubuntu/GPS-server` application was not inspected, copied,
modified, stopped, or reused. Its directory mtime remains 2026-07-24.

## 24. Confirmation: No System Package or Service Modified

No system package was installed, upgraded, or removed. No Nginx, Apache,
PHP-FPM, MySQL, firewall, DNS, or SSH configuration was changed. Only
project-local Composer and npm dependencies were downloaded into the project.

## 25. Blocking Issues

- None. All acceptance criteria (AC-02-01 through AC-02-14) are satisfied.
- Non-blocking note: optional Vite `fontaine` package not installed (font
  fallback optimization only); no action taken.

## 26. Recommended Next Task

Proceed to the next packet: configure MySQL 8.0.46 integration for deployment
(create a dedicated project database and least-privilege user, add MySQL env
configuration) while keeping SQLite for tests. Do not begin domain/business
feature implementation until its designated packet.
