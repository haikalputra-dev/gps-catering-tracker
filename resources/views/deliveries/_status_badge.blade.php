@php
    /** @var \App\Domain\Delivery\DeliveryStatus $status */
    use App\Domain\Delivery\DeliveryStatus;

    $config = match($status) {
        DeliveryStatus::Draft => ['variant' => 'neutral', 'icon' => 'pencil-square', 'label' => 'Draft'],
        DeliveryStatus::Scheduled => ['variant' => 'info', 'icon' => 'calendar-days', 'label' => 'Scheduled'],
        DeliveryStatus::InTransit => ['variant' => 'warning', 'icon' => 'truck', 'label' => 'In transit'],
        DeliveryStatus::Delivered => ['variant' => 'success', 'icon' => 'check-circle', 'label' => 'Delivered'],
        DeliveryStatus::Cancelled => ['variant' => 'danger', 'icon' => 'x-circle', 'label' => 'Cancelled'],
    };
@endphp
<x-badge :variant="$config['variant']">
    <x-dynamic-component :component="'heroicon-o-' . $config['icon']" class="w-3.5 h-3.5" />
    {{ $config['label'] }}
</x-badge>
