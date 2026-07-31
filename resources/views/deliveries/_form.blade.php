@php
    /** @var \App\Models\Delivery $delivery */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Kitchen> $kitchens */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Customer> $customers */

    $currentKitchenId = old('kitchen_id', $delivery->kitchen_id);
    $currentCustomerId = old('customer_id', $delivery->customer_id);
    $currentScheduledAt = old('scheduled_at');
    if ($currentScheduledAt === null && $delivery->scheduled_at !== null) {
        // Present UTC-stored scheduled_at as a Jakarta-local <input type=datetime-local> value.
        $currentScheduledAt = $delivery->scheduled_at
            ->copy()
            ->setTimezone(config('delivery.receipt_date_timezone', 'Asia/Jakarta'))
            ->format('Y-m-d\TH:i');
    }
    $currentNotes = old('notes', $delivery->notes);
    $currentCourierId = old('courier_id', $delivery->courier_id);
@endphp

<div class="space-y-5">
    <x-form-field name="kitchen_id" label="Kitchen" type="select" :required="true">
        <option value="">-- Select an active kitchen --</option>
        @foreach($kitchens as $kitchen)
            <option value="{{ $kitchen->id }}" @selected((int) $currentKitchenId === (int) $kitchen->id)>
                {{ $kitchen->code }} — {{ $kitchen->name }}
            </option>
        @endforeach
    </x-form-field>

    <x-form-field name="customer_id" label="Customer" type="select" :required="true">
        <option value="">-- Select an active customer --</option>
        @foreach($customers as $customer)
            <option value="{{ $customer->id }}" @selected((int) $currentCustomerId === (int) $customer->id)>
                {{ $customer->name }} ({{ $customer->phone }})
            </option>
        @endforeach
    </x-form-field>

    <x-form-field name="courier_id" label="Courier" type="select"
                  help="Optional on draft. Required when scheduling (AR-37).">
        <option value="">-- No courier assigned yet --</option>
        @foreach($couriers as $courier)
            <option value="{{ $courier->id }}" @selected((int) $currentCourierId === (int) $courier->id)>
                {{ $courier->name }}
            </option>
        @endforeach
    </x-form-field>

    <x-form-field
        name="scheduled_at"
        label="Scheduled at (Asia/Jakarta)"
        type="datetime-local"
        :value="$currentScheduledAt"
        help="Optional on draft. Required and must be in the future when scheduling." />

    <x-form-field
        name="notes"
        label="Notes"
        type="textarea"
        :value="$currentNotes"
        rows="4"
        maxlength="1000" />
</div>
