@extends('layouts.app')

@section('title', 'Add Account')

@section('content')
    <div class="card">
        <h1>Add Account</h1>
        <form method="POST" action="{{ route('owner.users.store') }}" novalidate>
            @csrf
            @include('owner.users._form', ['mode' => 'create', 'user' => null])
            <div style="margin-top: 16px; display: flex; gap: 8px;">
                <button type="submit">Create Account</button>
                <a href="{{ route('owner.users.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
