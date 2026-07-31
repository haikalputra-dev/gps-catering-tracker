@php
    /** @var \App\Domain\Delivery\DeliveryStatus $status */
@endphp
@switch($status)
    @case(\App\Domain\Delivery\DeliveryStatus::Draft)
        <x-badge variant="neutral">Draft</x-badge>
        @break
    @case(\App\Domain\Delivery\DeliveryStatus::Scheduled)
        <x-badge variant="info">Scheduled</x-badge>
        @break
    @case(\App\Domain\Delivery\DeliveryStatus::InTransit)
        <x-badge variant="warning">In transit</x-badge>
        @break
    @case(\App\Domain\Delivery\DeliveryStatus::Delivered)
        <x-badge variant="success">Delivered</x-badge>
        @break
    @case(\App\Domain\Delivery\DeliveryStatus::Cancelled)
        <x-badge variant="danger">Cancelled</x-badge>
        @break
@endswitch
