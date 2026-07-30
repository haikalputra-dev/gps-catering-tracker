@extends('layouts.app')

@section('title', 'Courier Dashboard')

@section('content')
    <div class="card">
        <h1>Courier Dashboard</h1>
        <p>Welcome, {{ auth()->user()->name }}.</p>
        <p class="placeholder">Delivery assignment and tracking controls are not implemented yet.</p>
    </div>
@endsection
