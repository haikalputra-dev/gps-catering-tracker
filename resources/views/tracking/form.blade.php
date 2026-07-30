@extends('layouts.public')

@section('title', 'Track your delivery')

@section('content')
    <div class="card">
        <h1>Track your delivery</h1>
        <p>Enter the receipt number from your order confirmation and the last four digits of the phone number on the order.</p>
        <form method="POST" action="{{ route('tracking.authenticate') }}" novalidate>
            @csrf
            <label for="receipt_number">Receipt number</label>
            <input
                type="text"
                id="receipt_number"
                name="receipt_number"
                value="{{ old('receipt_number') }}"
                placeholder="DEL-YYYYMMDD-XXXX"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                required
                aria-describedby="receipt-help">
            <p id="receipt-help" class="footer-note" style="text-align:left;margin:4px 0 0;">Format: DEL-YYYYMMDD-XXXX (case insensitive).</p>

            <label for="phone_last_four" style="margin-top:12px;">Last 4 digits of your phone</label>
            <input
                type="tel"
                id="phone_last_four"
                name="phone_last_four"
                inputmode="numeric"
                pattern="[0-9]{4}"
                maxlength="4"
                autocomplete="off"
                required
                aria-describedby="phone-help">
            <p id="phone-help" class="footer-note" style="text-align:left;margin:4px 0 0;">Digits only, no spaces.</p>

            <p style="margin-top:16px;">
                <button type="submit">Look up delivery</button>
            </p>
        </form>
    </div>
@endsection
