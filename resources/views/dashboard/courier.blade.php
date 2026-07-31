@php
    /** @var \App\Models\Delivery|null $activeDelivery */
    use App\Domain\Delivery\DeliveryStatus;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
@endphp
@extends('layouts.app')

@section('title', 'Courier Dashboard')

@section('content')
    <x-page-header
        title="Courier Dashboard"
        :subtitle="'Welcome, ' . auth()->user()->name . '.'" />

    @if($activeDelivery === null)
        <x-card>
            <div class="flex items-start gap-4">
                <div class="rounded-full bg-slate-100 p-4">
                    <svg class="w-8 h-8 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">No active delivery</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        You have no delivery assigned right now. Check back later or ask
                        the office to assign one to you.
                    </p>
                </div>
            </div>
        </x-card>
    @else
        <x-card>
            <div class="flex justify-between items-start flex-wrap gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        Delivery #{{ $activeDelivery->id }}
                        @if($activeDelivery->receipt_number)
                            <span class="text-sm font-normal text-slate-500">
                                &middot; <code class="text-slate-700">{{ $activeDelivery->receipt_number }}</code>
                            </span>
                        @endif
                    </h2>
                    <div class="mt-2">
                        @include('deliveries._status_badge', ['status' => $activeDelivery->status])
                    </div>
                </div>
                <x-button
                    :href="route('deliveries.show', $activeDelivery)"
                    variant="secondary">
                    View details
                </x-button>
            </div>
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-card title="Pickup">
                <p class="text-sm text-slate-700">
                    <span class="font-semibold text-slate-900">{{ $activeDelivery->kitchen_code }}</span>
                    &mdash; {{ $activeDelivery->kitchen_name }}
                </p>
                <p class="mt-1 text-sm text-slate-600">{{ $activeDelivery->kitchen_address }}</p>
            </x-card>
            <x-card title="Drop-off">
                <p class="text-sm text-slate-700">
                    <span class="font-semibold text-slate-900">{{ $activeDelivery->customer_name }}</span>
                    <span class="text-slate-500">({{ $activeDelivery->customer_phone }})</span>
                </p>
                <p class="mt-1 text-sm text-slate-600">{{ $activeDelivery->customer_address }}</p>
            </x-card>
        </div>

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
                @if($activeDelivery->status === DeliveryStatus::InTransit && $activeDelivery->dispatched_at)
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
    @endif
@endsection
