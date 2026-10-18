<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Admin security - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 24px; display: grid; place-items: center; background: #f1f5f9; color: #0f172a; font-family: system-ui, sans-serif; }
        main { width: 100%; max-width: 520px; padding: 32px; border-radius: 20px; border: 1px solid #e2e8f0; background: white; box-shadow: 0 20px 60px #0f172a14; }
        h1 { font-size: 25px; } h2 { font-size: 18px; } p { color: #475569; line-height: 1.6; }
        label { display: block; margin: 14px 0 6px; font-weight: 600; }
        input, select { width: 100%; padding: 12px; border: 1px solid #94a3b8; border-radius: 8px; font: inherit; }
        button, .button { display: block; width: 100%; margin: 16px 0; padding: 12px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font: inherit; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .secondary { background: #e2e8f0; color: #334155; } .danger { background: #b91c1c; }
        .alert { padding: 12px; border-radius: 8px; background: #fef2f2; color: #b91c1c; } .success { background: #ecfdf5; color: #166534; }
        details { margin: 20px 0; } summary { cursor: pointer; } .qr { margin: 20px auto; padding: 20px; width: fit-content; background: white; }
        .qr svg { display: block; max-width: 100%; height: auto; } code { overflow-wrap: anywhere; user-select: all; }
        input:focus, select:focus, button:focus { outline: 2px solid #4f46e5; outline-offset: 2px; }
    </style>
</head>
<body><main>
    <p>{{ config('app.name') }} &middot; Admin security</p>
    @if($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
    @yield('content')
</main></body>
</html>
