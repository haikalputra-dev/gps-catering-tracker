<?php

declare(strict_types=1);

namespace App\Http\Controllers\Telemetry;

use App\Domain\Device\TelemetryIngester;
use App\Domain\Device\TelemetryPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Telemetry\StoreTelemetryRequest;
use App\Models\Device;
use Illuminate\Http\Response;

/**
 * Ingests authenticated GPS telemetry submissions.
 *
 * The single `POST /api/telemetry` route enters here already gated by
 * (in order): `device.auth` → `throttle:telemetry` → payload validation
 * via {@see StoreTelemetryRequest}. The controller only has to bridge
 * the validated payload into {@see TelemetryIngester} and return the
 * uniform `204 No Content` response for both "persisted" and
 * "accepted-and-discarded" outcomes (AR-51, AR-52).
 */
class TelemetryController extends Controller
{
    public function __construct(private readonly TelemetryIngester $ingester)
    {
    }

    public function store(StoreTelemetryRequest $request): Response
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $this->ingester->ingest(
            $device,
            TelemetryPayload::fromValidated($request->validated()),
        );

        return response()->noContent();
    }
}
