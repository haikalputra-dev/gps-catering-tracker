@extends('layouts.app')

@section('title', 'Register device')

@section('content')
    <div class="card">
        <h1>Register device</h1>

        <form method="POST" action="{{ route('devices.store') }}">
            @csrf
            @include('devices._form', ['device' => null])
            <div style="margin-top: 16px;">
                <button type="submit">Register device</button>
                <a href="{{ route('devices.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
