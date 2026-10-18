@extends('layouts.admin')
@section('title', 'Security Settings')
@section('header', 'Security Settings')
@section('content')
@include('admin.settings._style')
<div class="settings-shell">
    <div class="settings-hero"><div><span class="settings-eyebrow">Your account</span><h1>Security &amp; MFA</h1><p>Manage your authenticator and recovery mobiles.</p></div></div>
    @include('admin.settings._tabs')
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <div class="settings-card"><div class="settings-card-body">
        <h2 class="settings-card-title">Multi-factor authentication: {{ ! $user->admin_mfa_required ? 'Disabled' : ($user->hasEnabledTwoFactorAuthentication() ? 'Enabled' : 'Setup required') }}</h2>
        <p>Either verified mobile can authorize authenticator recovery. These numbers belong to your security settings and are separate from your profile phone.</p>
        @if($user->adminRecoveryPhones())
            @foreach($user->admin_mfa_required ? ['reset' => 'Regenerate MFA QR', 'disable' => 'Disable MFA'] : ['enable' => 'Enable MFA'] as $action => $label)
                <details class="mb-3"><summary>{{ $label }}</summary>
                    <form method="POST" action="{{ route('admin-mfa.security.start') }}" class="mt-3">
                        @csrf<input type="hidden" name="action" value="{{ $action }}">
                        <label class="form-label">Current password<input class="form-control" type="password" name="password" autocomplete="current-password" required></label>
                        <label class="form-label">Send verification to<select class="form-select" name="recovery_slot">
                            @foreach($user->adminRecoveryPhones() as $phoneSlot => $phone)<option value="{{ $phoneSlot }}">Mobile {{ $phoneSlot }} — {{ \App\Support\AdminMfa::maskPhone($phone) }}</option>@endforeach
                        </select></label>
                        <button class="btn {{ $action === 'disable' ? 'btn-danger' : 'btn-primary' }}" type="submit">Verify mobile to continue</button>
                    </form>
                </details>
            @endforeach
        @else
            <div class="alert alert-info">Verify Mobile 1 below before continuing to your dashboard.</div>
        @endif
    </div></div>
    @foreach([1, 2] as $slot)
        <div class="settings-card"><div class="settings-card-body">
            <h2 class="settings-card-title">Mobile {{ $slot }} {{ $slot === 2 ? '(optional)' : '(required)' }}</h2>
            <p>{{ isset($user->adminRecoveryPhones()[$slot]) ? \App\Support\AdminMfa::maskPhone($user->adminRecoveryPhones()[$slot]).' — Verified' : 'Not registered' }}</p>
            <details><summary>{{ isset($user->adminRecoveryPhones()[$slot]) ? 'Change mobile' : 'Add mobile' }}</summary>
                <form method="POST" action="{{ route('admin-mfa.security.start') }}" class="mt-3">
                    @csrf<input type="hidden" name="action" value="phone"><input type="hidden" name="slot" value="{{ $slot }}">
                    <label class="form-label">New mobile with country code<input class="form-control" name="phone" type="tel" placeholder="+91 9876543210" maxlength="30" required></label>
                    @include('admin.settings.security-proof')
                    <button class="btn btn-primary" type="submit">Verify and save mobile</button>
                </form>
            </details>
            @if(count($user->adminRecoveryPhones()) === 2)
                <details class="mt-3"><summary>Remove Mobile {{ $slot }}</summary>
                    <p>At least one verified number will remain. If you remove Mobile 1, Mobile 2 becomes Mobile 1.</p>
                    <form method="POST" action="{{ route('admin-mfa.security.start') }}">
                        @csrf<input type="hidden" name="action" value="remove"><input type="hidden" name="slot" value="{{ $slot }}">
                        @include('admin.settings.security-proof', ['chooseMobile' => true])
                        <button class="btn btn-outline-danger" type="submit">Verify to remove</button>
                    </form>
                </details>
            @endif
        </div></div>
    @endforeach
</div>
@endsection
