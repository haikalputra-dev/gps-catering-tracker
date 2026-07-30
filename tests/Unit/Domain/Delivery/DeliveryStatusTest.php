<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use PHPUnit\Framework\TestCase;

class DeliveryStatusTest extends TestCase
{
    public function test_enum_values_are_stable_strings(): void
    {
        $this->assertSame('draft', DeliveryStatus::Draft->value);
        $this->assertSame('scheduled', DeliveryStatus::Scheduled->value);
        $this->assertSame('in_transit', DeliveryStatus::InTransit->value);
        $this->assertSame('delivered', DeliveryStatus::Delivered->value);
        $this->assertSame('cancelled', DeliveryStatus::Cancelled->value);
    }

    public function test_only_draft_is_editable(): void
    {
        $this->assertTrue(DeliveryStatus::Draft->isEditable());
        $this->assertFalse(DeliveryStatus::Scheduled->isEditable());
        $this->assertFalse(DeliveryStatus::InTransit->isEditable());
        $this->assertFalse(DeliveryStatus::Delivered->isEditable());
        $this->assertFalse(DeliveryStatus::Cancelled->isEditable());
    }

    public function test_active_for_concurrency_excludes_terminal_states(): void
    {
        $this->assertTrue(DeliveryStatus::Draft->isActiveForConcurrency());
        $this->assertTrue(DeliveryStatus::Scheduled->isActiveForConcurrency());
        $this->assertTrue(DeliveryStatus::InTransit->isActiveForConcurrency());
        $this->assertFalse(DeliveryStatus::Delivered->isActiveForConcurrency());
        $this->assertFalse(DeliveryStatus::Cancelled->isActiveForConcurrency());
    }

    public function test_terminal_states(): void
    {
        $this->assertFalse(DeliveryStatus::Draft->isTerminal());
        $this->assertFalse(DeliveryStatus::Scheduled->isTerminal());
        $this->assertFalse(DeliveryStatus::InTransit->isTerminal());
        $this->assertTrue(DeliveryStatus::Delivered->isTerminal());
        $this->assertTrue(DeliveryStatus::Cancelled->isTerminal());
    }

    public function test_allowed_transitions_from_draft(): void
    {
        $this->assertTrue(DeliveryStatus::Draft->canTransitionTo(DeliveryStatus::Scheduled));
        $this->assertTrue(DeliveryStatus::Draft->canTransitionTo(DeliveryStatus::Cancelled));
        $this->assertFalse(DeliveryStatus::Draft->canTransitionTo(DeliveryStatus::InTransit));
        $this->assertFalse(DeliveryStatus::Draft->canTransitionTo(DeliveryStatus::Delivered));
        $this->assertFalse(DeliveryStatus::Draft->canTransitionTo(DeliveryStatus::Draft));
    }

    public function test_allowed_transitions_from_scheduled(): void
    {
        $this->assertTrue(DeliveryStatus::Scheduled->canTransitionTo(DeliveryStatus::InTransit));
        $this->assertTrue(DeliveryStatus::Scheduled->canTransitionTo(DeliveryStatus::Cancelled));
        $this->assertFalse(DeliveryStatus::Scheduled->canTransitionTo(DeliveryStatus::Draft));
        $this->assertFalse(DeliveryStatus::Scheduled->canTransitionTo(DeliveryStatus::Delivered));
    }

    public function test_allowed_transitions_from_in_transit(): void
    {
        // AR-38 revised: mid-route cancellation is permitted so a courier
        // can abort a delivery that cannot be completed (customer no
        // longer available, road blocked, kitchen recall, etc.).
        $this->assertTrue(DeliveryStatus::InTransit->canTransitionTo(DeliveryStatus::Delivered));
        $this->assertTrue(DeliveryStatus::InTransit->canTransitionTo(DeliveryStatus::Cancelled));
        $this->assertFalse(DeliveryStatus::InTransit->canTransitionTo(DeliveryStatus::Scheduled));
        $this->assertFalse(DeliveryStatus::InTransit->canTransitionTo(DeliveryStatus::Draft));
    }

    public function test_terminal_states_cannot_transition_anywhere(): void
    {
        foreach (DeliveryStatus::cases() as $target) {
            $this->assertFalse(DeliveryStatus::Delivered->canTransitionTo($target));
            $this->assertFalse(DeliveryStatus::Cancelled->canTransitionTo($target));
        }
    }

    public function test_helpers_expose_case_groupings(): void
    {
        $this->assertSame(
            [DeliveryStatus::Draft, DeliveryStatus::Scheduled, DeliveryStatus::InTransit],
            DeliveryStatus::activeCases(),
        );
        $this->assertSame(
            [DeliveryStatus::Delivered, DeliveryStatus::Cancelled],
            DeliveryStatus::terminalCases(),
        );
        $this->assertSame(
            ['draft', 'scheduled', 'in_transit', 'delivered', 'cancelled'],
            DeliveryStatus::values(),
        );
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Draft', DeliveryStatus::Draft->label());
        $this->assertSame('Scheduled', DeliveryStatus::Scheduled->label());
        $this->assertSame('In transit', DeliveryStatus::InTransit->label());
        $this->assertSame('Delivered', DeliveryStatus::Delivered->label());
        $this->assertSame('Cancelled', DeliveryStatus::Cancelled->label());
    }
}
