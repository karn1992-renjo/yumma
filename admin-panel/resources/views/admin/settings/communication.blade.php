@extends('layouts.admin')

@section('title', 'Communication Settings')
@section('header', 'Communication Settings')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Communication Settings</h1>
            <p>Configure email delivery and message templates used by the platform.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Email Settings</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="redirect_to" value="admin.settings.communication">

                    <div class="row gy-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mail Driver</label>
                            <select name="mail_driver" class="form-select">
                                <option value="smtp" {{ ($settings['mail_driver'] ?? config('mail.default')) == 'smtp' ? 'selected' : '' }}>SMTP</option>
                                <option value="log" {{ ($settings['mail_driver'] ?? config('mail.default')) == 'log' ? 'selected' : '' }}>Log</option>
                                <option value="array" {{ ($settings['mail_driver'] ?? config('mail.default')) == 'array' ? 'selected' : '' }}>Array</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mail From Address</label>
                            <input type="email" name="mail_from_address" class="form-control" value="{{ $settings['mail_from_address'] ?? config('mail.from.address') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mail From Name</label>
                            <input type="text" name="mail_from_name" class="form-control" value="{{ $settings['mail_from_name'] ?? config('mail.from.name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SMTP Host</label>
                            <input type="text" name="mail_host" class="form-control" value="{{ $settings['mail_host'] ?? config('mail.mailers.smtp.host') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">SMTP Port</label>
                            <input type="number" name="mail_port" class="form-control" value="{{ $settings['mail_port'] ?? config('mail.mailers.smtp.port') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Encryption</label>
                            <select name="mail_encryption" class="form-select">
                                <option value="" {{ ($settings['mail_encryption'] ?? config('mail.mailers.smtp.scheme')) == '' ? 'selected' : '' }}>None</option>
                                <option value="tls" {{ ($settings['mail_encryption'] ?? config('mail.mailers.smtp.scheme')) == 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ ($settings['mail_encryption'] ?? config('mail.mailers.smtp.scheme')) == 'ssl' ? 'selected' : '' }}>SSL</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">SMTP Username</label>
                            <input type="text" name="mail_username" class="form-control" value="{{ $settings['mail_username'] ?? config('mail.mailers.smtp.username') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SMTP Password</label>
                            <input type="text" name="mail_password" class="form-control" value="{{ old('mail_password', $settings['mail_password'] ?? config('mail.mailers.smtp.password')) }}">
                            <div class="form-text">Saved password is shown here for direct review and editing.</div>
                        </div>
                    </div>

                    <hr>

                    <h5 class="mb-3 fw-semibold">Message Settings</h5>
                    <div class="row gy-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Message Service</label>
                            <select name="message_service" class="form-select">
                                <option value="">Select provider</option>
                                <option value="twilio" {{ ($settings['message_service'] ?? '') == 'twilio' ? 'selected' : '' }}>Twilio</option>
                                <option value="firebase" {{ ($settings['message_service'] ?? '') == 'firebase' ? 'selected' : '' }}>Firebase</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Login OTP Provider</label>
                            <select name="otp_service_provider" class="form-select" required>
                                <option value="" disabled {{ empty($settings['otp_service_provider'] ?? '') ? 'selected' : '' }}>Select OTP provider</option>
                                <option value="twilio" {{ ($settings['otp_service_provider'] ?? '') == 'twilio' ? 'selected' : '' }}>Twilio SMS</option>
                                <option value="firebase" {{ ($settings['otp_service_provider'] ?? '') == 'firebase' ? 'selected' : '' }}>Firebase</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Default Mobile Country Code</label>
                            <input type="text" name="default_mobile_country_code" class="form-control" value="{{ $settings['default_mobile_country_code'] ?? '+91' }}" placeholder="+91">
                            <div class="form-text">Used to prefill customer mobile auth and normalize phone numbers to E.164.</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Order Confirmation Template</label>
                            <textarea name="message_template_order_confirmation" class="form-control" rows="3">@if(!empty($settings['message_template_order_confirmation'])){{ $settings['message_template_order_confirmation'] }}@else Your order has been confirmed. Order number: {{ '{' . '{order_number}' . '}' }}.@endif</textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Delivery Update Template</label>
                            <textarea name="message_template_delivery_update" class="form-control" rows="3">@if(!empty($settings['message_template_delivery_update'])){{ $settings['message_template_delivery_update'] }}@else Your order is on the way. Order number: {{ '{' . '{order_number}' . '}' }}.@endif</textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">OTP / Verification Template</label>
                            <textarea name="message_template_otp" class="form-control" rows="3">@if(!empty($settings['message_template_otp'])){{ $settings['message_template_otp'] }}@else Your OTP code is {{ '{' . '{otp}' . '}' }}. It is valid for 10 minutes.@endif</textarea>
                        </div>
                    </div>

                    <hr>

                    <h5 class="mb-3 fw-semibold">Social Login</h5>
                    <div class="row gy-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Social Login</label>
                            <select name="social_login_enabled" class="form-select">
                                <option value="1" {{ ($settings['social_login_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ($settings['social_login_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Google Login</label>
                            <select name="social_login_google_enabled" class="form-select">
                                <option value="1" {{ ($settings['social_login_google_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ($settings['social_login_google_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Apple Login</label>
                            <select name="social_login_apple_enabled" class="form-select">
                                <option value="1" {{ ($settings['social_login_apple_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ($settings['social_login_apple_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Auto Register Customers</label>
                            <select name="social_login_auto_register" class="form-select">
                                <option value="1" {{ ($settings['social_login_auto_register'] ?? '1') == '1' ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ($settings['social_login_auto_register'] ?? '1') == '0' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Google Web Client ID</label>
                            <input type="text" name="social_login_google_web_client_id" class="form-control" value="{{ $settings['social_login_google_web_client_id'] ?? '' }}" placeholder="Firebase OAuth web client ID">
                            <div class="form-text">Used by the mobile app to request a Firebase-compatible Google ID token.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Apple Services ID</label>
                            <input type="text" name="social_login_apple_services_id" class="form-control" value="{{ $settings['social_login_apple_services_id'] ?? '' }}" placeholder="com.example.app.service">
                            <div class="form-text">Keep Apple provider enabled in Firebase Authentication before enabling this in production.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Auto Link Verified Email</label>
                            <select name="social_login_auto_link_verified_email" class="form-select">
                                <option value="1" {{ ($settings['social_login_auto_link_verified_email'] ?? '1') == '1' ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ($settings['social_login_auto_link_verified_email'] ?? '1') == '0' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <div class="form-text">Only verified Firebase social email tokens are accepted for linking.</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">Save Communication Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
