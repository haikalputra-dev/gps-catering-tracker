@extends('layouts.app')

@section('title', 'Edit device')

@section('content')
    <div class="card">
        <h1>Edit device</h1>

        <form method="POST" action="{{ route('devices.update', $device) }}">
            @csrf
            @method('PUT')
            @include('devices._form', ['device' => $device])
            <div style="margin-top: 16px;">
                <button type="submit">Save changes</button>
                <a href="{{ route('devices.show', $device) }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
