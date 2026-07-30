@php
    /** @var \App\Domain\Delivery\DeliveryStatus $status */
@endphp
@switch($status)
    @case(\App\Domain\Delivery\DeliveryStatus::Draft)
        <span style="padding:2px 8px;border-radius:12px;background:#e5e7eb;color:#374151;font-size:0.8rem;">Draft</span>
        @break
    @case(\App\Domain\Delivery\DeliveryStatus::Scheduled)
        <span style="padding:2px 8px;border-radius:12px;background:#dbeafe;color:#1e40af;font-size:0.8rem;">Scheduled</span>
        @break
    @case(\App\Domain\Delivery\DeliveryStatus::InTransit)
        <span style="padding:2px 8px;border-radius:12px;background:#fef3c7;color:#92400e;font-size:0.8rem;">In transit</span>
        @break
    @case(\App\Domain\Delivery\DeliveryStatus::Delivered)
        <span style="padding:2px 8px;border-radius:12px;background:#d1fae5;color:#065f46;font-size:0.8rem;">Delivered</span>
        @break
    @case(\App\Domain\Delivery\DeliveryStatus::Cancelled)
        <span style="padding:2px 8px;border-radius:12px;background:#fee2e2;color:#991b1b;font-size:0.8rem;">Cancelled</span>
        @break
@endswitch
