<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Delivery Tracking')</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f5f5f5; color: #222; }
        header { background: #1f2933; color: #fff; padding: 12px 24px; }
        header strong { font-size: 1.05rem; }
        main { max-width: 720px; margin: 24px auto; padding: 0 16px; }
        .card { background: #fff; padding: 16px 24px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
        .flash { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; background: #ecfdf5; color: #065f46; }
        .info { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; background: #eff6ff; color: #1e3a8a; }
        .errors { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; background: #fef2f2; color: #991b1b; }
        .errors ul { margin: 0; padding-left: 20px; }
        label { display: block; margin: 8px 0 4px; font-weight: 600; }
        input[type=text], input[type=tel] {
            width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 4px;
            font-family: inherit; font-size: 1rem;
        }
        button, .btn { background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-family: inherit; font-size: 0.95rem; }
        button.secondary, .btn.secondary { background: #6b7280; }
        form.inline { display: inline; }
        .footer-note { color: #6b7280; font-size: 0.85rem; margin-top: 16px; text-align: center; }
        dl.summary { margin: 0; display: grid; grid-template-columns: max-content 1fr; column-gap: 16px; row-gap: 6px; }
        dl.summary dt { font-weight: 600; color: #374151; }
        dl.summary dd { margin: 0; color: #111827; }
        ol.timeline { list-style: none; padding: 0; margin: 0; }
        ol.timeline li { padding: 10px 0; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; gap: 12px; }
        ol.timeline li:last-child { border-bottom: 0; }
        ol.timeline .label { font-weight: 600; }
        ol.timeline .pending { color: #9ca3af; font-style: italic; }
        ol.timeline .done { color: #065f46; }
        ol.timeline .cancelled { color: #991b1b; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
        .status-scheduled { background: #e0e7ff; color: #3730a3; }
        .status-in_transit { background: #fef3c7; color: #92400e; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        h1 { margin-top: 0; }
        h2 { margin-top: 0; font-size: 1.1rem; }
    </style>
</head>
<body>
    <header>
        <strong>Delivery Tracking</strong>
    </header>
    <main>
        @if(session('info'))
            <div class="info">{{ session('info') }}</div>
        @endif
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
        <p class="footer-note">GPS Catering Tracker</p>
    </main>
</body>
</html>
