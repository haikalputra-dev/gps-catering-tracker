@extends('layouts.app')

@section('title', 'Edit Account')

@section('content')
    <div class="card">
        <h1>Edit Account: {{ $user->name }}</h1>
        <form method="POST" action="{{ route('owner.users.update', $user) }}" novalidate>
            @csrf
            @method('PUT')
            @include('owner.users._form', ['mode' => 'edit', 'user' => $user])
            <div style="margin-top: 16px; display: flex; gap: 8px;">
                <button type="submit">Save Changes</button>
                <a href="{{ route('owner.users.index') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
