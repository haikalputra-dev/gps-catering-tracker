@props(['currentStatus', 'timestamps' => []])

@php
    use Illuminate\Support\Carbon;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
    $current = $currentStatus->value ?? $currentStatus;

    $steps = [
        ['key' => 'draft',      'label' => 'Draft',      'icon' => 'pencil-square', 'ts' => 'created_at'],
        ['key' => 'scheduled',  'label' => 'Scheduled',  'icon' => 'calendar-days', 'ts' => 'scheduled_at_recorded'],
        ['key' => 'in_transit', 'label' => 'Dispatched', 'icon' => 'truck',         'ts' => 'in_transit_at'],
        ['key' => 'delivered',  'label' => 'Delivered',  'icon' => 'check-circle',  'ts' => 'delivered_at'],
    ];

    $isCancelled = $current === 'cancelled';

    $reached = match ($current) {
        'draft'      => ['draft'],
        'scheduled'  => ['draft', 'scheduled'],
        'in_transit' => ['draft', 'scheduled', 'in_transit'],
        'delivered'  => ['draft', 'scheduled', 'in_transit', 'delivered'],
        default      => [],
    };

    $formatTs = static function ($value) use ($displayTz) {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->timezone($displayTz)->format('d M H:i');
        } catch (\Throwable $e) {
            return null;
        }
    };
@endphp

@if($isCancelled)
    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-start gap-3">
            <x-heroicon-o-x-circle class="w-6 h-6 text-red-600 shrink-0" />
            <div class="min-w-0">
                <p class="font-semibold text-red-900">Cancelled</p>
                @php $cancelledAtDisplay = $formatTs($timestamps['cancelled_at'] ?? null); @endphp
                @if($cancelledAtDisplay)
                    <p class="text-sm text-red-700 mt-0.5">{{ $cancelledAtDisplay }}</p>
                @endif
                @if(!empty($timestamps['cancellation_reason']))
                    <p class="text-sm text-red-700 mt-1">Reason: {{ $timestamps['cancellation_reason'] }}</p>
                @endif
            </div>
        </div>
    </div>
@else
    {{-- Desktop: horizontal --}}
    <div class="hidden md:flex items-start justify-between gap-2">
        @foreach($steps as $i => $step)
            @php
                $isReached = in_array($step['key'], $reached, true);
                $isCurrent = $step['key'] === $current;
                $circleClass = $isReached
                    ? ($isCurrent ? 'bg-orange-600 text-white ring-4 ring-orange-100' : 'bg-orange-600 text-white')
                    : 'bg-slate-100 text-slate-400 border-2 border-slate-200';
                $labelClass = $isReached ? 'text-slate-900 font-semibold' : 'text-slate-400';
                $nextReached = isset($steps[$i + 1]) && in_array($steps[$i + 1]['key'], $reached, true);
                $lineClass = ($isReached && $nextReached) ? 'bg-orange-600' : 'bg-slate-200';
                $ts = $formatTs($timestamps[$step['ts']] ?? null);
            @endphp
            <div class="flex flex-col items-center flex-1 min-w-0">
                <div class="flex items-center w-full">
                    <div class="flex-1 h-0.5 {{ $i === 0 ? 'bg-transparent' : ($isReached ? 'bg-orange-600' : 'bg-slate-200') }}"></div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 {{ $circleClass }}">
                        @if($isReached && ! $isCurrent)
                            <x-heroicon-s-check class="w-6 h-6" />
                        @else
                            <x-dynamic-component :component="'heroicon-o-' . $step['icon']" class="w-6 h-6" />
                        @endif
                    </div>
                    <div class="flex-1 h-0.5 {{ $i === count($steps) - 1 ? 'bg-transparent' : $lineClass }}"></div>
                </div>
                <div class="mt-3 text-center">
                    <p class="text-sm {{ $labelClass }}">{{ $step['label'] }}</p>
                    @if($ts && $isReached)
                        <p class="text-xs text-slate-500 mt-0.5">{{ $ts }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Mobile: horizontal scroll --}}
    <div class="md:hidden overflow-x-auto -mx-4 px-4 pb-2">
        <div class="flex items-center gap-3 min-w-max">
            @foreach($steps as $i => $step)
                @php
                    $isReached = in_array($step['key'], $reached, true);
                    $isCurrent = $step['key'] === $current;
                    $ringClass = $isCurrent ? ' ring-4 ring-orange-100' : '';
                    $circleClass = $isReached
                        ? 'bg-orange-600 text-white' . $ringClass
                        : 'bg-slate-100 text-slate-400 border-2 border-slate-200';
                    $labelClass = $isReached ? 'text-slate-900 font-semibold' : 'text-slate-400';
                @endphp
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $circleClass }}">
                        @if($isReached && ! $isCurrent)
                            <x-heroicon-s-check class="w-5 h-5" />
                        @else
                            <x-dynamic-component :component="'heroicon-o-' . $step['icon']" class="w-5 h-5" />
                        @endif
                    </div>
                    <p class="mt-1 text-xs {{ $labelClass }}">{{ $step['label'] }}</p>
                </div>
                @if($i < count($steps) - 1)
                    <div class="h-0.5 w-8 bg-slate-200"></div>
                @endif
            @endforeach
        </div>
    </div>
@endif
