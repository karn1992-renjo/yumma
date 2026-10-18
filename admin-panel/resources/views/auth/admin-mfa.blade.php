<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin verification - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f1f5f9; color: #0f172a; font-family: system-ui, sans-serif; }
        main { width: 100%; max-width: 460px; padding: 32px; background: white; border: 1px solid #e2e8f0; border-radius: 24px; box-shadow: 0 20px 60px #0f172a14; }
        h1 { margin: 8px 0 16px; font-size: 25px; }
        p { color: #475569; line-height: 1.6; }
        .eyebrow { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #4f46e5; }
        .qr { width: fit-content; padding: 20px; margin: 20px auto; background: white; }
        .qr svg { display: block; max-width: 100%; height: auto; }
        code { overflow-wrap: anywhere; }
        .secret { display: block; margin: 12px 0; padding: 12px; background: #f8fafc; text-align: center; user-select: all; }
        label { display: block; margin: 20px 0 8px; font-weight: 600; }
        input { width: 100%; padding: 14px; border: 1px solid #94a3b8; border-radius: 8px; font: inherit; }
        input:focus { outline: 2px solid #4f46e5; outline-offset: 2px; }
        button, .continue { display: block; width: 100%; margin-top: 20px; padding: 14px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font: inherit; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .cancel { background: transparent; color: #475569; }
        .errors { padding: 12px; background: #fef2f2; color: #b91c1c; border-radius: 8px; }
        details { margin-top: 20px; }
        summary { cursor: pointer; color: #475569; }
        .codes { padding: 16px; background: #f8fafc; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 380px) { main { padding: 20px; } .codes { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">{{ config('app.name') }} · Admin security</div>
    @if(isset($recoveryCodes))
        <h1>Save your recovery codes</h1>
        <p>Your authenticator is registered. Store these codes somewhere safe. Each code can be used once if you lose access to your authenticator.</p>
        <div class="codes">@foreach($recoveryCodes as $recoveryCode)<code>{{ $recoveryCode }}</code>@endforeach</div>
        <a class="continue" href="{{ route('admin.dashboard') }}">Continue to admin</a>
    @else
        <h1>{{ $setup ? 'Set up your authenticator' : 'Verify your admin login' }}</h1>
        @if($setup)
            <p>Scan this QR code with Google Authenticator, Microsoft Authenticator, or another authenticator app. Then enter its 6-digit code to finish registration.</p>
            <div class="qr" role="img" aria-label="Authenticator registration QR code">{!! $qrCode !!}</div>
            <details><summary>Cannot scan the QR code?</summary><p>Enter this setup key in your authenticator and choose a time-based account.</p><code class="secret">{{ $secret }}</code></details>
        @else
            <p>Enter the 6-digit code from your authenticator app to continue.</p>
        @endif
        @if($errors->any())
            <div class="errors" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('admin-mfa.verify') }}">
            @csrf
            <label for="code">Authentication code</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
            <button type="submit">{{ $setup ? 'Register authenticator' : 'Verify and log in' }}</button>
        </form>
        @if(! $setup)
            <details>
                <summary>Forgot authenticator?</summary>
                @php($recoveryPhones = \App\Support\AdminMfa::pendingUser(request())->adminRecoveryPhones())
                @if($recoveryPhones)
                    <p>Verify either saved mobile to register a new authenticator.</p>
                    <form method="POST" action="{{ route('admin-mfa.security.start') }}">
                        @csrf<input type="hidden" name="action" value="reset">
                        <label for="recovery_slot">Recovery mobile</label>
                        <select id="recovery_slot" name="recovery_slot" style="width:100%;padding:12px">
                            @foreach($recoveryPhones as $slot => $phone)<option value="{{ $slot }}">Mobile {{ $slot }} — {{ \App\Support\AdminMfa::maskPhone($phone) }}</option>@endforeach
                        </select>
                        <button type="submit">Send SMS verification code</button>
                    </form>
                @else
                    <p>No recovery mobile is registered yet. Use an existing recovery code to log in and register one.</p>
                @endif
            </details>
            <details>
                <summary>Use a recovery code</summary>
                <form method="POST" action="{{ route('admin-mfa.verify') }}">
                    @csrf
                    <label for="recovery_code">Recovery code</label>
                    <input id="recovery_code" name="recovery_code" type="text" autocomplete="off" maxlength="100" required>
                    <button type="submit">Verify recovery code</button>
                </form>
            </details>
        @endif
        <form method="POST" action="{{ route('admin-mfa.cancel') }}">@csrf<button class="cancel" type="submit">Back to login</button></form>
    @endif
</main>
</body>
</html>
