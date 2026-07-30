<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GPS Catering Tracker')</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f5f5f5; color: #222; }
        header { background: #1f2933; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-right: 16px; }
        header .right { display: flex; align-items: center; gap: 12px; }
        main { max-width: 960px; margin: 24px auto; padding: 0 16px; }
        .card { background: #fff; padding: 16px 24px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
        .flash { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; background: #ecfdf5; color: #065f46; }
        .errors { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; background: #fef2f2; color: #991b1b; }
        .errors ul { margin: 0; padding-left: 20px; }
        label { display: block; margin: 8px 0 4px; font-weight: 600; }
        input[type=text], input[type=email], input[type=password], select {
            width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 4px;
        }
        button, .btn { background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        button.secondary, .btn.secondary { background: #6b7280; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .placeholder { color: #6b7280; font-style: italic; }
        form.inline { display: inline; }
    </style>
</head>
<body>
    <header>
        <div>
            <a href="{{ url('/') }}"><strong>GPS Catering Tracker</strong></a>
            @auth
                <a href="{{ route('dashboard') }}">Dashboard</a>
                @if(auth()->user()->isOwner())
                    <a href="{{ route('owner.users.index') }}">User Accounts</a>
                @endif
                @if(auth()->user()->isOwner() || auth()->user()->isStaff())
                    <a href="{{ route('kitchens.index') }}">Kitchens</a>
                    <a href="{{ route('customers.index') }}">Customers</a>
                    <a href="{{ route('deliveries.index') }}">Deliveries</a>
                @endif
            @endauth
        </div>
        <div class="right">
            @auth
                <span>{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="secondary">Log out</button>
                </form>
            @endauth
        </div>
    </header>
    <main>
        @if(session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="errors">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
