@extends('layouts.admin-security')
@section('content')
    @if(! $challenge->verified_at)
        @if(($challenge->payload['verification_transport'] ?? 'sms') === 'firebase')
            <h1>Verify with Firebase</h1>
            <p>MSG91 could not deliver this verification. Firebase Phone Auth can verify {{ \App\Support\AdminMfa::maskPhone($challenge->payload['phone']) }} instead.</p>
            @if($challenge->purpose === 'phone_authorize')<p>First verify your current recovery mobile. You will then verify the new number separately.</p>@endif
            <div id="firebase-status" class="alert" role="status" hidden></div>
            <div id="recaptcha-container"></div>
            <button id="firebase-send" type="button">Send code with Firebase</button>
            <div id="firebase-code-step" hidden>
                <label for="firebase-code">Firebase verification code</label>
                <input id="firebase-code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6">
                <button id="firebase-confirm" type="button">Verify Firebase code</button>
            </div>
            <form id="firebase-token-form" method="POST" action="{{ route('admin-mfa.security.firebase') }}" hidden>
                @csrf<input id="firebase-id-token" type="hidden" name="firebase_id_token">
            </form>
            <form method="POST" action="{{ route('admin-mfa.security.resend') }}">@csrf<button type="submit" class="secondary">Retry MSG91</button></form>
            <script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
            <script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-auth-compat.js"></script>
            <script>
                (() => {
                    const config = @json($firebaseConfig);
                    const phone = @json($challenge->payload['phone']);
                    const status = document.getElementById('firebase-status');
                    const sendButton = document.getElementById('firebase-send');
                    const confirmButton = document.getElementById('firebase-confirm');
                    const codeStep = document.getElementById('firebase-code-step');
                    let confirmation = null;
                    const showStatus = (message) => {
                        status.textContent = message;
                        status.hidden = false;
                    };
                    const friendlyError = (error) => {
                        const code = String(error && error.code || 'firebase/error');
                        if (code.includes('too-many-requests')) return 'Firebase has temporarily limited verification attempts. Please wait and try again.';
                        if (code.includes('invalid-phone-number')) return 'Firebase rejected the configured mobile number.';
                        if (code.includes('invalid-verification-code')) return 'The Firebase code is invalid or expired.';
                        if (code.includes('unauthorized-domain')) return 'This website domain must be authorized in Firebase Authentication settings.';
                        return 'Firebase verification could not be completed (' + code.replace('auth/', '') + ').';
                    };
                    try {
                        if (!window.firebase || !config || !config.apiKey || !config.projectId) {
                            throw {code: 'firebase/configuration-missing'};
                        }
                        if (!firebase.apps.length) firebase.initializeApp(config);
                        const auth = firebase.auth();
                        auth.useDeviceLanguage();
                        const verifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {size: 'normal'});
                        sendButton.addEventListener('click', async () => {
                            sendButton.disabled = true;
                            try {
                                confirmation = await auth.signInWithPhoneNumber(phone, verifier);
                                codeStep.hidden = false;
                                showStatus('Firebase sent a verification code.');
                            } catch (error) {
                                showStatus(friendlyError(error));
                                sendButton.disabled = false;
                                try { verifier.render().then((widgetId) => window.grecaptcha && grecaptcha.reset(widgetId)); } catch (_) {}
                            }
                        });
                        confirmButton.addEventListener('click', async () => {
                            const code = document.getElementById('firebase-code').value.trim();
                            if (!confirmation || !/^[0-9]{6}$/.test(code)) {
                                showStatus('Enter the 6-digit Firebase code.');
                                return;
                            }
                            confirmButton.disabled = true;
                            try {
                                const credential = await confirmation.confirm(code);
                                document.getElementById('firebase-id-token').value = await credential.user.getIdToken(true);
                                document.getElementById('firebase-token-form').submit();
                            } catch (error) {
                                showStatus(friendlyError(error));
                                confirmButton.disabled = false;
                            }
                        });
                    } catch (error) {
                        showStatus(friendlyError(error));
                        sendButton.disabled = true;
                    }
                })();
            </script>
        @else
            <h1>Verify your mobile</h1>
            <p>Enter the 6-digit SMS code sent to {{ \App\Support\AdminMfa::maskPhone($challenge->payload['phone']) }}. It expires in 5 minutes.</p>
            @if($challenge->purpose === 'phone_authorize')<p>First verify your current recovery mobile. Then we will send a separate code to your new number.</p>@endif
            <form method="POST" action="{{ route('admin-mfa.security.verify') }}">
                @csrf
                <label for="otp">SMS verification code</label>
                <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
                <button type="submit">Verify mobile</button>
            </form>
            <form method="POST" action="{{ route('admin-mfa.security.resend') }}">@csrf<button type="submit" class="secondary">Resend code</button></form>
            <p>You can resend after 60 seconds. Only the latest code will work.</p>
        @endif
    @elseif($challenge->purpose === 'disable')
        <h1>Disable MFA?</h1>
        <p>Your mobile has been verified. Disabling MFA removes your authenticator and recovery codes. Future admin logins will use your password alone.</p>
        <form method="POST" action="{{ route('admin-mfa.security.confirm') }}">
            @csrf<input type="hidden" name="confirm" value="1">
            <button type="submit" class="danger">Confirm: disable MFA</button>
        </form>
    @else
        <h1>{{ $challenge->purpose === 'enable' ? 'Enable your authenticator' : 'Register your new authenticator' }}</h1>
        <p>Scan this QR with your authenticator app, then enter its 6-digit code to finish. Your security settings change only after confirmation.</p>
        <div class="qr" role="img" aria-label="New authenticator QR code">{!! $qrCode !!}</div>
        <details><summary>Cannot scan the QR?</summary><p>Use this key for a time-based account:</p><code>{{ $secret }}</code></details>
        <form method="POST" action="{{ route('admin-mfa.security.confirm') }}">
            @csrf<input type="hidden" name="confirm" value="1">
            <label for="code">New authenticator code</label>
            <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
            <button type="submit">Confirm new authenticator</button>
        </form>
    @endif
    <form method="POST" action="{{ route('admin-mfa.security.cancel') }}">@csrf<button type="submit" class="secondary">Cancel / choose another mobile</button></form>
@endsection
