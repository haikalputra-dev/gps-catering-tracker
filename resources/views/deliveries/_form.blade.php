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
@endphp

<label for="kitchen_id">Kitchen</label>
<select id="kitchen_id" name="kitchen_id" required>
    <option value="">-- Select an active kitchen --</option>
    @foreach($kitchens as $kitchen)
        <option value="{{ $kitchen->id }}" @selected((int) $currentKitchenId === (int) $kitchen->id)>
            {{ $kitchen->code }} — {{ $kitchen->name }}
        </option>
    @endforeach
</select>

<label for="customer_id">Customer</label>
<select id="customer_id" name="customer_id" required>
    <option value="">-- Select an active customer --</option>
    @foreach($customers as $customer)
        <option value="{{ $customer->id }}" @selected((int) $currentCustomerId === (int) $customer->id)>
            {{ $customer->name }} ({{ $customer->phone }})
        </option>
    @endforeach
</select>

<label for="scheduled_at">Scheduled at (Asia/Jakarta)</label>
<input
    type="datetime-local"
    id="scheduled_at"
    name="scheduled_at"
    value="{{ $currentScheduledAt }}"
    style="width:100%;padding:8px;box-sizing:border-box;border:1px solid #d1d5db;border-radius:4px;"
/>
<small style="color:#6b7280;">Optional on draft. Required and must be in the future when scheduling.</small>

<label for="notes">Notes</label>
<textarea
    id="notes"
    name="notes"
    rows="4"
    maxlength="1000"
    style="width:100%;padding:8px;box-sizing:border-box;border:1px solid #d1d5db;border-radius:4px;font-family:inherit;"
>{{ $currentNotes }}</textarea>
