# Telemetry Retention

Telemetry rows in `telemetry_records` are retained for a bounded
window and then permanently deleted by a scheduled purge command.

## Retention window

The window is set by `config('telemetry.retention_days')`, which reads
`TELEMETRY_RETENTION_DAYS` from the environment. The default is
**30 days** measured from each row's `received_at` timestamp
(AR-48).

Values `<= 0` are treated as a configuration error by the purge
command and cause it to exit with a non-zero status. If you need to
retain telemetry indefinitely during development, do not run the
purge command — do not set retention to zero.

## Purge command

```
php artisan telemetry:purge
```

Deletes every row in `telemetry_records` where `received_at` is
older than `now() - retention_days` (UTC). Logs the deletion count
and cutoff at `info` level to the default log channel.

```
php artisan telemetry:purge --dry-run
```

Reports how many rows *would* be deleted and exits without changing
the database. Useful when tuning the retention window or verifying
schedule behaviour on a quiet system.

Exit codes:

- `0` — success (dry run, no-op, or delete complete).
- `1` — `telemetry.retention_days` is missing or not a positive
  integer.

## Schedule

Registered in `routes/console.php`:

```php
Schedule::command('telemetry:purge')->dailyAt('03:00');
```

Runs once per day at **03:00 in the application timezone**
(Asia/Jakarta, AR-13). Off-peak by design. This is a single-server
prototype: no `withoutOverlapping()` or `onOneServer()` guard is
needed, and none is configured.

Confirm the schedule at any time:

```
php artisan schedule:list
```

## Scope

The purge command only touches `telemetry_records`. Under no
circumstances does it delete or modify:

- `devices`
- `device_assignments`
- any delivery, kitchen, customer, or user row

Device rows carry their own lifecycle (active/inactive via
`devices.is_active`, AR-52); assignment rows are the historical
audit trail of courier binding (AR-50). Both are permanent by
design.

The purge is **age-based only**. It does not consider delivery
status, courier identity, or device identity. If a delivery
completes and its telemetry rows fall outside the retention
window, they are deleted at the next scheduled run.
