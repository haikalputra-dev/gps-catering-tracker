@extends('layouts.app')

@section('title', 'Owner Dashboard')

@section('content')
    <div class="card">
        <h1>Owner Dashboard</h1>
        <p>Welcome, {{ auth()->user()->name }}.</p>
        <p>Use the User Accounts page to manage staff and courier accounts.</p>
        <p class="placeholder">Kitchen management, delivery scheduling, pricing, customer tracking and device features are not implemented yet.</p>
    </div>
@endsection
