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
     * Packets 07 and 08 exercised only the `draft → scheduled`,
 * `draft → cancelled`, and `scheduled → cancelled` transitions.
 * Packet 09 activates `scheduled → in_transit`, `in_transit → delivered`,
 * and `in_transit → cancelled` (AR-38 revised).
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
     * Allowed transitions (final matrix per AR-23 as revised by AR-38):
     *   draft      -> scheduled | cancelled
     *   scheduled  -> in_transit | cancelled
     *   in_transit -> delivered | cancelled
     *   delivered  -> (none; terminal)
     *   cancelled  -> (none; terminal)
     *
     * Packet 09 activates `in_transit → cancelled` for mid-route
     * cancellation; the assigned courier (or owner/staff) may cancel a
     * delivery that is already on the road. Preservation of frozen
     * values on cancellation continues to be handled by
     * `DeliveryCanceller`.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Scheduled, self::Cancelled], true),
            self::Scheduled => in_array($target, [self::InTransit, self::Cancelled], true),
            self::InTransit => in_array($target, [self::Delivered, self::Cancelled], true),
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
