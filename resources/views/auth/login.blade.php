@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="card">
        <h1>Log in</h1>
        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <div style="margin-top: 16px;">
                <button type="submit">Log in</button>
            </div>
        </form>
    </div>
@endsection
