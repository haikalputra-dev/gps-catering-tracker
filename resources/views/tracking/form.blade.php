@extends('layouts.public')

@section('title', 'Track your delivery')

@section('content')
    <x-card>
        <div class="text-center mb-6">
            <div class="mx-auto w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-orange-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Track your delivery</h1>
            <p class="mt-2 text-sm text-slate-600">
                Enter the receipt number from your order confirmation and the last four digits of the phone number on the order.
            </p>
        </div>

        <form method="POST" action="{{ route('tracking.authenticate') }}" novalidate class="space-y-5">
            @csrf

            <x-form-field
                name="receipt_number"
                label="Receipt number"
                type="text"
                :value="old('receipt_number')"
                placeholder="DEL-YYYYMMDD-XXXX"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                :required="true"
                aria-describedby="receipt-help"
                help="Format: DEL-YYYYMMDD-XXXX (case insensitive)." />

            <x-form-field
                name="phone_last_four"
                label="Last 4 digits of your phone"
                type="tel"
                inputmode="numeric"
                pattern="[0-9]{4}"
                maxlength="4"
                autocomplete="off"
                :required="true"
                aria-describedby="phone-help"
                help="Digits only, no spaces." />

            <x-button type="submit" class="w-full justify-center">
                Look up delivery
            </x-button>
        </form>
    </x-card>
@endsection
