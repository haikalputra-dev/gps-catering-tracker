<?php

declare(strict_types=1);

namespace App\Domain\Device\Exceptions;

use RuntimeException;

/**
 * Raised when the target Device is inactive (`is_active = false`) and
 * therefore cannot participate in an assignment operation or in
 * telemetry ingestion.
 */
class InactiveDeviceException extends RuntimeException
{
    public static function forDeviceId(int $deviceId): self
    {
        return new self(sprintf(
            'Device %d is inactive.',
            $deviceId,
        ));
    }
}
