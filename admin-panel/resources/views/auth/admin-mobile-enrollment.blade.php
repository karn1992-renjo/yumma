@extends('layouts.admin-security')
@section('content')
    <h1>Register your recovery mobile</h1>
    <p>Verify at least one mobile to recover your authenticator if you lose it. A second mobile is optional; either verified number can be used.</p>
    @foreach([1, 2] as $slot)
        @if(isset($user->adminRecoveryPhones()[$slot]))
            <p>Mobile {{ $slot }}: {{ \App\Support\AdminMfa::maskPhone($user->adminRecoveryPhones()[$slot]) }} — Verified</p>
        @elseif($slot === 1 || $user->adminRecoveryPhones())
            <details @if($slot === 1) open @endif>
                <summary>Mobile {{ $slot }} {{ $slot === 2 ? '(optional)' : '(required)' }}</summary>
                <form method="POST" action="{{ route('admin-mfa.security.start') }}">
                    @csrf
                    <input type="hidden" name="action" value="phone">
                    <input type="hidden" name="slot" value="{{ $slot }}">
                    <label for="phone-{{ $slot }}">Mobile with country code</label>
                    <input id="phone-{{ $slot }}" name="phone" type="tel" autocomplete="tel" maxlength="30" value="{{ $slot === 1 ? $user->phone : '' }}" placeholder="+91 9876543210" required>
                    <button type="submit">Send verification code</button>
                </form>
            </details>
        @endif
    @endforeach
    @if($user->adminRecoveryPhones())
        <form method="POST" action="{{ route('admin-mfa.continue') }}">@csrf<button type="submit">Continue</button></form>
    @endif
    <form method="POST" action="{{ route('admin-mfa.cancel') }}">@csrf<button class="secondary" type="submit">Back to login</button></form>
@endsection
