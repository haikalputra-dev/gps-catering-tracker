@php
    /** @var \App\Models\Delivery|null $activeDelivery */
    use App\Domain\Delivery\DeliveryStatus;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
@endphp
@extends('layouts.app')

@section('title', 'Courier Dashboard')

@section('content')
    <x-page-header
        title="My Delivery"
        :subtitle="'Welcome, ' . auth()->user()->name . '.'" />

    @if($activeDelivery === null)
        <div class="bg-white rounded-lg border border-slate-200 py-16 px-6 text-center">
            <x-heroicon-o-inbox class="w-16 h-16 mx-auto text-slate-300" />
            <h2 class="mt-4 text-lg font-semibold text-slate-900">No active delivery</h2>
            <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                You have no delivery assigned right now. This page will update
                once the office assigns one to you.
            </p>
        </div>
    @else
        @php
            $isScheduled = $activeDelivery->status === DeliveryStatus::Scheduled;
            $isInTransit = $activeDelivery->status === DeliveryStatus::InTransit;
        @endphp

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            {{-- Hero header with receipt and status --}}
            <div class="px-6 py-6 bg-gradient-to-r from-orange-50 to-orange-100 border-b border-slate-200">
                <div class="flex items-start justify-between flex-wrap gap-3">
                    <div>
                        <p class="text-xs font-medium text-orange-700 uppercase tracking-wide">Receipt</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">
                            @if($activeDelivery->receipt_number)
                                {{ $activeDelivery->receipt_number }}
                            @else
                                Delivery #{{ $activeDelivery->id }}
                            @endif
                        </p>
                    </div>
                    <div>
                        @include('deliveries._status_badge', ['status' => $activeDelivery->status])
                    </div>
                </div>
            </div>

            {{-- Kitchen and customer info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-200">
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <x-heroicon-o-building-storefront class="w-5 h-5 text-slate-500" />
                        <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Pickup</span>
                    </div>
                    <p class="text-sm font-semibold text-slate-900">
                        <span>{{ $activeDelivery->kitchen_code }}</span>
                        <span class="text-slate-500">&mdash; {{ $activeDelivery->kitchen_name }}</span>
                    </p>
                    <p class="mt-1 text-sm text-slate-600">{{ $activeDelivery->kitchen_address }}</p>
                </div>

                <div class="p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <x-heroicon-o-map-pin class="w-5 h-5 text-slate-500" />
                        <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Deliver to</span>
                    </div>
                    <p class="text-sm font-semibold text-slate-900">{{ $activeDelivery->customer_name }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $activeDelivery->customer_address }}</p>
                    @if($activeDelivery->customer_phone)
                        <a href="tel:{{ $activeDelivery->customer_phone }}" class="mt-2 inline-flex items-center gap-1 text-sm text-orange-600 hover:text-orange-700 font-medium">
                            <x-heroicon-o-phone class="w-4 h-4" />
                            {{ $activeDelivery->customer_phone }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Schedule --}}
        <x-card title="Schedule">
            <dl class="space-y-3 text-sm">
                <div class="flex flex-wrap gap-2">
                    <dt class="font-medium text-slate-700 w-32">Scheduled for:</dt>
                    <dd class="text-slate-900">
                        @if($activeDelivery->scheduled_at)
                            {{ $activeDelivery->scheduled_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                            <span class="text-xs text-slate-500">({{ $displayTz }})</span>
                        @else
                            <span class="text-slate-400">Not set</span>
                        @endif
                    </dd>
                </div>
                @if($isInTransit && $activeDelivery->dispatched_at)
                    <div class="flex flex-wrap gap-2">
                        <dt class="font-medium text-slate-700 w-32">Dispatched at:</dt>
                        <dd class="text-slate-900">
                            {{ $activeDelivery->dispatched_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                            <span class="text-xs text-slate-500">({{ $displayTz }})</span>
                        </dd>
                    </div>
                @endif
                @if($activeDelivery->notes)
                    <div>
                        <dt class="font-medium text-slate-700 mb-1">Notes:</dt>
                        <dd class="text-slate-700 whitespace-pre-wrap">{{ $activeDelivery->notes }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        {{--
            Fee is intentionally omitted from the courier dashboard (AR-40).
            The Pricing card is DOM-absent for couriers, so the fee never
            reaches the browser for a courier session.
        --}}

        <x-card>
            @include('deliveries._action_buttons', ['delivery' => $activeDelivery])
        </x-card>

        <div>
            <x-button :href="route('deliveries.show', $activeDelivery)" variant="secondary">
                <x-heroicon-o-document-text class="w-4 h-4" />
                View full details
            </x-button>
        </div>
    @endif
@endsection
