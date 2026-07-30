@php
    use App\Domain\Delivery\DeliveryStatus;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
@endphp
@extends('layouts.app')

@section('title', 'Deliveries')

@section('content')
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h1 style="margin:0;font-size:1.4rem;">Deliveries</h1>
            <a class="btn" href="{{ route('deliveries.create') }}">New Delivery</a>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('deliveries.index') }}">
            <label for="status">Filter by status</label>
            <select id="status" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach($statusOptions as $option)
                    <option value="{{ $option->value }}"
                        @selected($statusFilter?->value === $option->value)>
                        {{ $option->label() }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="card">
        @if($deliveries->isEmpty())
            <p class="placeholder">No deliveries match the current filter.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Receipt</th>
                        <th>Status</th>
                        <th>Kitchen</th>
                        <th>Customer</th>
                        <th>Courier</th>
                        <th>Scheduled ({{ $displayTz }})</th>
                        <th>Fee</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deliveries as $delivery)
                        <tr>
                            <td>#{{ $delivery->id }}</td>
                            <td>
                                @if($delivery->receipt_number)
                                    <code>{{ $delivery->receipt_number }}</code>
                                @else
                                    <span class="placeholder">—</span>
                                @endif
                            </td>
                            <td>@include('deliveries._status_badge', ['status' => $delivery->status])</td>
                            <td>
                                @if($delivery->status === DeliveryStatus::Draft)
                                    {{ $delivery->kitchen?->code }} — {{ $delivery->kitchen?->name }}
                                @else
                                    {{ $delivery->kitchen_code }} — {{ $delivery->kitchen_name }}
                                @endif
                            </td>
                            <td>
                                @if($delivery->status === DeliveryStatus::Draft)
                                    {{ $delivery->customer?->name }}
                                @else
                                    {{ $delivery->customer_name }}
                                @endif
                            </td>
                            <td>
                                @if($delivery->courier)
                                    {{ $delivery->courier->name }}
                                @else
                                    <span class="placeholder">—</span>
                                @endif
                            </td>
                            <td>
                                @if($delivery->scheduled_at)
                                    {{ $delivery->scheduled_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                                @else
                                    <span class="placeholder">—</span>
                                @endif
                            </td>
                            <td>
                                @if($delivery->fee_rupiah !== null)
                                    Rp {{ number_format((int) $delivery->fee_rupiah, 0, ',', '.') }}
                                @else
                                    <span class="placeholder">—</span>
                                @endif
                            </td>
                            <td>{{ optional($delivery->created_at)->format('Y-m-d') }}</td>
                            <td>
                                <a href="{{ route('deliveries.show', $delivery) }}">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:12px;">
                {{ $deliveries->links() }}
            </div>
        @endif
    </div>
@endsection
