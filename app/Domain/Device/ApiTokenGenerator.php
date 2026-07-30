<?php

declare(strict_types=1);

namespace App\Domain\Device;

use App\Models\Device;
use RuntimeException;

/**
 * Generates cryptographically random API tokens for devices.
 *
 * Tokens are drawn from the configured `telemetry.token_alphabet` using
 * `random_int` and are `telemetry.token_length` characters long. The
 * generator retries up to {@see MAX_ATTEMPTS} times on uniqueness
 * collision. The default alphabet of 62 characters at length 40 yields
 * ~238 bits of entropy, making an actual collision astronomically
 * unlikely; the retry loop exists to make the collision failure mode
 * loud rather than silent (mirrors ReceiptNumberGenerator).
 *
 * Tokens are returned as plaintext. The caller is responsible for
 * persisting the token to `devices.api_token` and for surfacing it to
 * the owner exactly once at creation or rotation time. AR-47 requires
 * plaintext storage so `hash_equals` comparison is possible in
 * middleware; the surrounding operational controls (admin-only CRUD,
 * `$hidden` on the model, no display in edit forms) are the mitigation.
 */
class ApiTokenGenerator
{
    /**
     * Maximum number of random-token attempts before we abort.
     */
    public const MAX_ATTEMPTS = 10;

    /**
     * Generate a unique API token suitable for `devices.api_token`.
     *
     * @throws RuntimeException If configuration is invalid or every
     *                          attempt collides with an existing row.
     */
    public function generate(): string
    {
        $alphabet = $this->configuredAlphabet();
        $length = $this->configuredLength();

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $candidate = $this->randomToken($alphabet, $length);

            if (! $this->tokenExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf(
            'Failed to generate a unique device API token after %d attempts.',
            self::MAX_ATTEMPTS,
        ));
    }

    private function tokenExists(string $token): bool
    {
        return Device::query()
            ->where('api_token', $token)
            ->exists();
    }

    private function configuredLength(): int
    {
        $length = (int) config('telemetry.token_length', 40);

        if ($length < 16) {
            throw new RuntimeException(
                'Device API token length must be >= 16 characters.',
            );
        }

        return $length;
    }

    private function configuredAlphabet(): string
    {
        $alphabet = (string) config('telemetry.token_alphabet', '');

        if (strlen($alphabet) < 16) {
            throw new RuntimeException(
                'Device API token alphabet must have at least 16 characters.',
            );
        }

        return $alphabet;
    }

    /**
     * Pull $length characters from $alphabet using `random_int`. This
     * matches the entropy source of ReceiptNumberGenerator and is
     * suitable for tokens; the alphabet includes a-zA-Z0-9 by default.
     */
    private function randomToken(string $alphabet, int $length): string
    {
        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
