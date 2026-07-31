@props(['currentStatus', 'timestamps' => []])

@php
    use Illuminate\Support\Carbon;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
    $current = $currentStatus->value ?? $currentStatus;

    $steps = [
        ['key' => 'scheduled',  'label' => 'Scheduled',  'description' => 'Your delivery has been scheduled.', 'icon' => 'calendar-days', 'ts' => 'scheduled_at_recorded'],
        ['key' => 'in_transit', 'label' => 'Dispatched', 'description' => 'Your courier is on the way.',       'icon' => 'truck',         'ts' => 'in_transit_at'],
        ['key' => 'delivered',  'label' => 'Delivered',  'description' => 'Your delivery has arrived.',        'icon' => 'check-circle',  'ts' => 'delivered_at'],
    ];

    $isCancelled = $current === 'cancelled';

    $reached = match ($current) {
        'scheduled'  => ['scheduled'],
        'in_transit' => ['scheduled', 'in_transit'],
        'delivered'  => ['scheduled', 'in_transit', 'delivered'],
        default      => [],
    };

    // For cancelled from-scheduled state, we still want to show Scheduled as reached.
    if ($isCancelled && !empty($timestamps['scheduled_at_recorded'])) {
        $reached[] = 'scheduled';
    }
    if ($isCancelled && !empty($timestamps['in_transit_at'])) {
        $reached[] = 'in_transit';
    }
    $reached = array_values(array_unique($reached));

    $formatTs = static function ($value) use ($displayTz) {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->timezone($displayTz)->format('D, d M Y H:i');
        } catch (\Throwable $e) {
            return null;
        }
    };
@endphp

<ol class="relative">
    @foreach($steps as $i => $step)
        @php
            $isReached = in_array($step['key'], $reached, true);
            $isCurrent = $step['key'] === $current;
            $isLast = $i === count($steps) - 1 && ! $isCancelled;

            $circleClass = $isReached
                ? ($isCurrent ? 'bg-orange-600 text-white ring-4 ring-orange-100' : 'bg-emerald-500 text-white')
                : 'bg-slate-100 text-slate-400 border-2 border-slate-200';

            $labelClass = $isReached ? 'text-slate-900' : 'text-slate-400';
            $descClass  = $isReached ? 'text-slate-600' : 'text-slate-400';

            $nextReached = isset($steps[$i + 1]) && in_array($steps[$i + 1]['key'], $reached, true);
            $lineClass = $isReached && $nextReached ? 'bg-emerald-500' : 'bg-slate-200';

            $ts = $formatTs($timestamps[$step['ts']] ?? null);
        @endphp
        <li class="flex gap-4 pb-8 relative">
            @unless($isLast)
                <div class="absolute left-6 top-12 -ml-px w-0.5 h-full {{ $lineClass }}"></div>
            @endunless

            <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 z-10 {{ $circleClass }}">
                @if($isReached && ! $isCurrent)
                    <x-heroicon-s-check class="w-6 h-6" />
                @else
                    <x-dynamic-component :component="'heroicon-o-' . $step['icon']" class="w-6 h-6" />
                @endif
            </div>

            <div class="flex-1 pt-1.5 min-w-0">
                <p class="text-base font-semibold {{ $labelClass }}">{{ $step['label'] }}</p>
                <p class="text-sm {{ $descClass }} mt-0.5">{{ $step['description'] }}</p>
                @if($ts && $isReached)
                    <p class="text-xs text-slate-500 mt-1">{{ $ts }}</p>
                @elseif(! $isReached && ! $isCancelled)
                    <p class="text-xs text-slate-400 mt-1 italic">Pending</p>
                @endif
            </div>
        </li>
    @endforeach

    @if($isCancelled)
        @php
            $cancelledAtDisplay = $formatTs($timestamps['cancelled_at'] ?? null);
            $reason = $timestamps['cancellation_reason'] ?? null;
        @endphp
        <li class="flex gap-4 relative">
            <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 z-10 bg-red-500 text-white ring-4 ring-red-100">
                <x-heroicon-o-x-circle class="w-6 h-6" />
            </div>
            <div class="flex-1 pt-1.5 min-w-0">
                <p class="text-base font-semibold text-red-900">Cancelled</p>
                @if($cancelledAtDisplay)
                    <p class="text-xs text-slate-500 mt-1">{{ $cancelledAtDisplay }}</p>
                @endif
                @if($reason)
                    <p class="text-sm text-slate-700 mt-2 whitespace-pre-wrap">
                        <span class="font-medium">Reason:</span> {{ $reason }}
                    </p>
                @endif
            </div>
        </li>
    @endif
</ol>
