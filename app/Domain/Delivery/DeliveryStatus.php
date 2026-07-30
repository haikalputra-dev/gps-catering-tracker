<?php

declare(strict_types=1);

namespace App\Domain\Delivery;

/**
 * Delivery lifecycle status.
 *
 * Stored values are exactly `draft`, `scheduled`, `in_transit`, `delivered`
 * and `cancelled`. These strings are persisted in the `deliveries.status`
 * column and MUST NOT change (AR-23).
 *
 * Packet 07 exercises only the `draft -> scheduled`, `draft -> cancelled`
 * and `scheduled -> cancelled` transitions. `scheduled -> in_transit` and
 * `in_transit -> delivered` are declared in {@see canTransitionTo()} for
 * completeness but are not wired to any route in this packet.
 */
enum DeliveryStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Human-readable label used in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::InTransit => 'In transit',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * True when the delivery may still be edited by owner/staff.
     *
     * Only drafts are editable. Scheduled deliveries carry immutable
     * snapshots and receipt numbers and must not be mutated in place.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * True when this status counts against the concurrency cap (AR-27).
     *
     * Terminal statuses (`delivered`, `cancelled`) do NOT count.
     */
    public function isActiveForConcurrency(): bool
    {
        return match ($this) {
            self::Draft, self::Scheduled, self::InTransit => true,
            self::Delivered, self::Cancelled => false,
        };
    }

    /**
     * True when this status is terminal (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Whether a transition from this status to $target is allowed.
     *
     * Allowed transitions:
     *   draft      -> scheduled | cancelled
     *   scheduled  -> in_transit | cancelled
     *   in_transit -> delivered
     *
     * Packet 07 routes only trigger the draft/scheduled outgoing edges.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Scheduled, self::Cancelled], true),
            self::Scheduled => in_array($target, [self::InTransit, self::Cancelled], true),
            self::InTransit => $target === self::Delivered,
            self::Delivered, self::Cancelled => false,
        };
    }

    /**
     * All statuses that are non-terminal.
     *
     * @return array<int, self>
     */
    public static function activeCases(): array
    {
        return [self::Draft, self::Scheduled, self::InTransit];
    }

    /**
     * All statuses that are terminal.
     *
     * @return array<int, self>
     */
    public static function terminalCases(): array
    {
        return [self::Delivered, self::Cancelled];
    }

    /**
     * All raw string values, useful for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
