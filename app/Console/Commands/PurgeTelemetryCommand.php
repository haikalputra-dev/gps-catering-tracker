<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TelemetryRecord;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * `telemetry:purge` — fulfills AR-48's deferred retention worker.
 *
 * Deletes rows from `telemetry_records` whose `received_at` is older
 * than `config('telemetry.retention_days')` days. The window is a
 * single global age-based cutoff:
 *
 *   - not per delivery status,
 *   - not per courier,
 *   - not per device.
 *
 * `devices` and `device_assignments` are NEVER touched by this
 * command. Only `telemetry_records` rows are affected.
 *
 * Scheduling: registered in `routes/console.php` to run daily at
 * 03:00 in the application timezone (Asia/Jakarta).
 *
 * Options:
 *   --dry-run    Report how many rows WOULD be deleted, without
 *                deleting anything. Exit code 0 on success.
 *
 * Exit codes:
 *   0 — success (dry run, no-op, or delete complete).
 *   1 — misconfiguration (retention_days missing or <= 0).
 */
class PurgeTelemetryCommand extends Command
{
    protected $signature = 'telemetry:purge
        {--dry-run : Report how many rows would be deleted without deleting anything}';

    protected $description = 'Delete telemetry_records rows older than the configured retention window (AR-48).';

    public function handle(): int
    {
        $retentionDays = config('telemetry.retention_days');

        if (! is_int($retentionDays) || $retentionDays <= 0) {
            $this->error(sprintf(
                'telemetry.retention_days is misconfigured: expected a positive integer, got %s. '
                . 'Set TELEMETRY_RETENTION_DAYS to a positive integer (default 30).',
                var_export($retentionDays, true),
            ));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $cutoff = CarbonImmutable::now('UTC')->subDays($retentionDays);
        $cutoffLabel = $cutoff->format('Y-m-d H:i:s');

        $count = TelemetryRecord::query()
            ->where('received_at', '<', $cutoff)
            ->count();

        if ($dryRun) {
            $this->info(sprintf(
                'Dry run: would delete %d telemetry rows older than %s UTC (%d day retention).',
                $count,
                $cutoffLabel,
                $retentionDays,
            ));

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info(sprintf(
                'No telemetry rows older than %s UTC to delete.',
                $cutoffLabel,
            ));

            return self::SUCCESS;
        }

        $deleted = TelemetryRecord::query()
            ->where('received_at', '<', $cutoff)
            ->delete();

        Log::info('telemetry:purge deleted rows', [
            'deleted' => $deleted,
            'cutoff_utc' => $cutoffLabel,
            'retention_days' => $retentionDays,
        ]);

        $this->info(sprintf(
            'Deleted %d telemetry rows older than %s UTC (%d day retention).',
            $deleted,
            $cutoffLabel,
            $retentionDays,
        ));

        return self::SUCCESS;
    }
}
