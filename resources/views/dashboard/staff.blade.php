@extends('layouts.app')

@section('title', 'Staff Dashboard')

@section('content')
    <div class="card">
        <h1>Staff Dashboard</h1>
        <p>Welcome, {{ auth()->user()->name }}.</p>
        <p class="placeholder">Kitchen and delivery scheduling functions are not implemented yet.</p>
    </div>
@endsection
