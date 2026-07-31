@extends('layouts.app')

@section('title', 'Staff Dashboard')

@section('content')
    <x-page-header
        title="Staff Dashboard"
        :subtitle="'Welcome back, ' . auth()->user()->name . '.'" />

    {{-- Stats row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            label="Kitchens"
            :value="$stats['kitchens_total']"
            :sublabel="$stats['kitchens_active'] . ' active'"
            icon="kitchen"
            :href="route('kitchens.index')" />
        <x-stat-card
            label="Customers"
            :value="$stats['customers_active']"
            :sublabel="$stats['customers_total'] . ' total'"
            icon="customer"
            :href="route('customers.index')" />
        <x-stat-card
            label="In Progress"
            :value="$stats['deliveries_in_progress']"
            sublabel="Deliveries active now"
            icon="truck"
            :href="route('deliveries.index')" />
        <x-stat-card
            label="This Week"
            :value="$stats['deliveries_this_week']"
            sublabel="Delivered in last 7 days"
            icon="check" />
    </div>

    {{-- Recent activity --}}
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Recent Deliveries</h2>
            <a href="{{ route('deliveries.index') }}" class="text-sm text-red-600 hover:text-red-700 font-medium">
                View all &rarr;
            </a>
        </div>

        @if($recent_deliveries->isEmpty())
            <x-empty-state
                icon="truck"
                title="No deliveries yet"
                description="Schedule your first delivery to see it appear here."
                actionLabel="Create your first delivery"
                :actionHref="route('deliveries.create')"
            />
        @else
            <ul class="divide-y divide-slate-200">
                @foreach($recent_deliveries as $d)
                    <li>
                        <a href="{{ route('deliveries.show', $d) }}" class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="p-2 bg-slate-100 rounded-lg">
                                    <x-heroicon-o-truck class="w-5 h-5 text-slate-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">
                                        {{ $d->receipt_number ?? 'Draft #' . $d->id }}
                                    </p>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ $d->kitchen_name ?? optional($d->kitchen)->name }}
                                        &rarr;
                                        {{ $d->customer_name ?? optional($d->customer)->name }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                @include('deliveries._status_badge', ['status' => $d->status])
                                <span class="text-xs text-slate-400">
                                    {{ $d->created_at->diffForHumans() }}
                                </span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
