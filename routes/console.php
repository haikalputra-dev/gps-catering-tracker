<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Telemetry retention purge (AR-48, Packet 13). Runs once per day at
| 03:00 in the application timezone (Asia/Jakarta, AR-13). Off-peak by
| design; a single-server prototype does not need overlap or
| single-server guards.
|
*/

Schedule::command('telemetry:purge')->dailyAt('03:00');
