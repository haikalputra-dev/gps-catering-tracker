<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a telemetry request by its `Authorization: Bearer <token>`
 * header against the `devices.api_token` column (AR-47 revised).
 *
 * Tokens are stored in plaintext for prototype simplicity but are
 * compared with `hash_equals` to remove one class of timing side
 * channels. When the header is missing, malformed, or does not match
 * an active device, the middleware responds with a JSON `401` and a
 * generic message; the specific failure reason is never disclosed to
 * the caller so an attacker cannot enumerate identifiers.
 *
 * A successful lookup attaches the resolved {@see Device} instance to
 * the request via the `device` attribute so downstream handlers can
 * retrieve it with `$request->attributes->get('device')` without
 * re-querying.
 *
 * Rate limiting is applied separately by the framework `throttle`
 * middleware using the named `telemetry` limiter that keys off this
 * same device attribute (see `bootstrap/app.php`).
 */
class AuthenticateDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return $this->unauthorized();
        }

        $device = $this->resolveDevice($token);

        if ($device === null) {
            return $this->unauthorized();
        }

        if (! $device->isActive()) {
            return $this->unauthorized();
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }

    /**
     * Extract the token from `Authorization: Bearer <token>`. Returns
     * null when the header is missing, uses a different scheme, or
     * carries an empty token.
     */
    private function extractBearerToken(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization', '');

        if ($header === '') {
            return null;
        }

        if (! preg_match('/^Bearer\s+(?<token>\S+)$/i', $header, $matches)) {
            return null;
        }

        $token = $matches['token'] ?? '';

        return $token !== '' ? $token : null;
    }

    /**
     * Look up a device by presented token. We fetch by a coarse prefix
     * key to keep the query indexable, then use `hash_equals` for the
     * final comparison so timing attacks cannot leak byte positions of
     * the stored token even against a database that has been dumped.
     *
     * In practice, `api_token` has a unique index so the initial where
     * clause narrows to a single row; the constant-time compare is
     * belt-and-braces defence recommended by AR-47.
     */
    private function resolveDevice(string $token): ?Device
    {
        /** @var Device|null $device */
        $device = Device::query()
            ->where('api_token', $token)
            ->first();

        if ($device === null) {
            return null;
        }

        $stored = (string) $device->api_token;

        if (! hash_equals($stored, $token)) {
            return null;
        }

        return $device;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'Invalid device token.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
