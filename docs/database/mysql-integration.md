# MySQL Integration

Reference for the runtime database used by the GPS Catering Tracker
application on the local VPS.

## Purpose

Document how the Laravel application authenticates to MySQL, what the
database and user are named, how privileges are scoped, and how to
reprovision or rotate credentials without touching the host's MySQL
configuration.

See `docs/decisions/ADR-006-database-environment-strategy.md` for the
rationale behind the runtime/test split.

## Runtime configuration

| Field       | Value                                                  |
|-------------|--------------------------------------------------------|
| Engine      | MySQL 8.0.46 (Ubuntu 24.04 package)                    |
| Bind        | `127.0.0.1:3306` (loopback only, unchanged from host)  |
| Database    | `gps_catering_tracker`                                 |
| Charset     | `utf8mb4`                                              |
| Collation   | `utf8mb4_unicode_ci`                                   |
| App user    | `gps_catering_app@localhost`                           |
| Privileges  | `ALL PRIVILEGES` scoped to `gps_catering_tracker.*`    |
| Credentials | Stored only in the project `.env` (mode 600)           |

The application user has no global privileges, no `GRANT OPTION`, and no
access to any other schema on the host.

## Environment variables

Set in `/home/ubuntu/gps-catering-tracker/.env`:

```
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=gps_catering_tracker
DB_USERNAME=gps_catering_app
DB_PASSWORD=<random 48-hex-character secret>
```

`.env` is git-ignored. `.env.example` continues to advertise SQLite as the
portable default so a fresh clone can boot without MySQL.

## Test configuration

`phpunit.xml` overrides the database connection for the test suite:

```
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE"   value=":memory:"/>
```

Tests never touch MySQL, never require credentials, and never leave state
behind on disk.

## Provisioning procedure (idempotent-safe recreation)

If the database or user must be recreated from scratch, run the following
as a MySQL admin (e.g. via `sudo mysql`). Generate a fresh password first
so no secret is echoed to the shell.

```
# 1. Generate a password without printing it
umask 077
DB_PASSWORD="$(php -r 'echo bin2hex(random_bytes(24));')"

# 2. Write a mode-600 SQL file
SQL_FILE="$(mktemp -t gpsct-init-XXXXXXXX.sql)"
chmod 600 "$SQL_FILE"
cat > "$SQL_FILE" <<SQL
CREATE DATABASE \`gps_catering_tracker\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
CREATE USER 'gps_catering_app'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`gps_catering_tracker\`.* TO 'gps_catering_app'@'localhost';
SQL

# 3. Apply and delete the SQL file
sudo mysql < "$SQL_FILE"
rm -f "$SQL_FILE"

# 4. Copy the password into .env manually (never via history), then:
unset DB_PASSWORD
```

The password never appears in `~/.bash_history`, shell output, or any
tracked file.

## Credential rotation

To rotate the application password:

1. Generate a new secret with `php -r 'echo bin2hex(random_bytes(24));'`.
2. Update `mysql.user` via `ALTER USER 'gps_catering_app'@'localhost'
   IDENTIFIED BY '<new>'` (executed from a mode-600 temp file, same
   pattern as above).
3. Update `DB_PASSWORD` in `.env`.
4. Run `php artisan config:clear` and re-verify with `php artisan tinker
   --execute="DB::connection()->getPdo();"`.

Do not `FLUSH PRIVILEGES` unless a manual `INSERT` into `mysql.user` was
performed (not the case here).

## Grants verification

```
SHOW GRANTS FOR 'gps_catering_app'@'localhost';
```

Expected output (order may vary):

```
GRANT USAGE ON *.* TO `gps_catering_app`@`localhost`
GRANT ALL PRIVILEGES ON `gps_catering_tracker`.* TO `gps_catering_app`@`localhost`
```

`USAGE` is MySQL's placeholder for "no global privileges"; it must not be
mistaken for actual access.

## Host boundaries

- Host binding (`bind-address`), authentication plugin defaults, socket
  paths, and firewall rules were **not** modified by this integration.
- Port 3306 remains loopback-only. External exposure would require an
  explicit follow-up packet.
- The neighbouring project at `/home/ubuntu/GPS-server` is out of scope.
  Its database (if any) is untouched by this integration.

## Backup and restore (informational, not automated)

No backup automation is provisioned yet. For manual snapshots:

```
sudo mysqldump --single-transaction --routines --triggers \
    gps_catering_tracker > gps_catering_tracker.sql
```

Restore path is deferred until the schema contains real data worth
protecting; tracked in the risk register.
