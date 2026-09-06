@extends('layouts.admin')

@section('title', 'Communication Settings')
@section('header', 'Communication Settings')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-envelope"></i> Messaging Stack</span>
            <h1>Communication Settings</h1>
            <p>Configure email delivery, SMS/OTP behavior, message templates, and social login options used by customer account flows.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Communication Providers</h2>
                <p class="settings-card-subtitle">Provider values are saved as application settings and reused across notification and authentication workflows.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.communication">

                <div class="settings-section-title">Email Delivery</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Mail Driver</label>
                        <select name="mail_driver" class="form-select">
                            <option value="smtp" {{ ($settings['mail_driver'] ?? config('mail.default')) == 'smtp' ? 'selected' : '' }}>SMTP</option>
                            <option value="log" {{ ($settings['mail_driver'] ?? config('mail.default')) == 'log' ? 'selected' : '' }}>Log</option>
                            <option value="array" {{ ($settings['mail_driver'] ?? config('mail.default')) == 'array' ? 'selected' : '' }}>Array</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">From Email</label>
                        <input type="email" name="mail_from_address" class="form-control" value="{{ $settings['mail_from_address'] ?? config('mail.from.address') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">From Name</label>
                        <input type="text" name="mail_from_name" class="form-control" value="{{ $settings['mail_from_name'] ?? config('mail.from.name') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="mail_host" class="form-control" value="{{ $settings['mail_host'] ?? config('mail.mailers.smtp.host') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="mail_port" class="form-control" value="{{ $settings['mail_port'] ?? config('mail.mailers.smtp.port') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Encryption</label>
                        <select name="mail_encryption" class="form-select">
                            <option value="" {{ empty($settings['mail_encryption']) ? 'selected' : '' }}>None</option>
                            <option value="tls" {{ ($settings['mail_encryption'] ?? config('mail.mailers.smtp.encryption')) == 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ ($settings['mail_encryption'] ?? '') == 'ssl' ? 'selected' : '' }}>SSL</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="mail_username" class="form-control" value="{{ $settings['mail_username'] ?? config('mail.mailers.smtp.username') }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">SMTP Password</label>
                        <input type="text" name="mail_password" class="form-control" value="{{ old('mail_password', $settings['mail_password'] ?? config('mail.mailers.smtp.password')) }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">SMS & OTP</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Message Service</label>
                        <select name="message_service" class="form-select">
                            <option value="">Select provider</option>
                            <option value="twilio" {{ ($settings['message_service'] ?? '') == 'twilio' ? 'selected' : '' }}>Twilio</option>
                            <option value="msg91" {{ ($settings['message_service'] ?? '') == 'msg91' ? 'selected' : '' }}>MSG91</option>
                            <option value="exotel" {{ ($settings['message_service'] ?? '') == 'exotel' ? 'selected' : '' }}>Exotel</option>
                            <option value="firebase" {{ ($settings['message_service'] ?? '') == 'firebase' ? 'selected' : '' }}>Firebase</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">OTP Provider</label>
                        <select name="otp_service_provider" class="form-select" required>
                            <option value="" disabled {{ empty($settings['otp_service_provider'] ?? '') ? 'selected' : '' }}>Select OTP provider</option>
                            <option value="twilio" {{ ($settings['otp_service_provider'] ?? '') == 'twilio' ? 'selected' : '' }}>Twilio SMS</option>
                            <option value="msg91" {{ ($settings['otp_service_provider'] ?? '') == 'msg91' ? 'selected' : '' }}>MSG91 OTP</option>
                            <option value="exotel" {{ ($settings['otp_service_provider'] ?? '') == 'exotel' ? 'selected' : '' }}>Exotel SMS</option>
                            <option value="firebase" {{ ($settings['otp_service_provider'] ?? '') == 'firebase' ? 'selected' : '' }}>Firebase Phone Auth</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Default Mobile Country Code</label>
                        <input type="text" name="default_mobile_country_code" class="form-control" value="{{ $settings['default_mobile_country_code'] ?? '+91' }}" placeholder="+91">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Order Confirmation Template</label>
                        <textarea name="message_template_order_confirmation" class="form-control" rows="3">@if(!empty($settings['message_template_order_confirmation'])){{ $settings['message_template_order_confirmation'] }}@else Your order has been confirmed. Order number: {{ '{' . '{order_number}' . '}' }}.@endif</textarea>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Delivery Update Template</label>
                        <textarea name="message_template_delivery_update" class="form-control" rows="3">@if(!empty($settings['message_template_delivery_update'])){{ $settings['message_template_delivery_update'] }}@else Your order is on the way. Order number: {{ '{' . '{order_number}' . '}' }}.@endif</textarea>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">OTP Template</label>
                        <textarea name="message_template_otp" class="form-control" rows="3">@if(!empty($settings['message_template_otp'])){{ $settings['message_template_otp'] }}@else Your OTP code is {{ '{' . '{otp}' . '}' }}. It is valid for 10 minutes.@endif</textarea>
                    </div>
                </div>

                <div class="settings-section-title mt-4">MSG91 Settings</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">MSG91 Auth Key</label>
                        <input type="text" name="msg91_authkey" class="form-control" value="{{ old('msg91_authkey', $settings['msg91_authkey'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">MSG91 OTP Mode</label>
                        <select name="msg91_otp_mode" class="form-select">
                            <option value="widget" {{ ($settings['msg91_otp_mode'] ?? 'widget') == 'widget' ? 'selected' : '' }}>Widget/SDK for mobile apps</option>
                            <option value="api" {{ ($settings['msg91_otp_mode'] ?? 'widget') == 'api' ? 'selected' : '' }}>SendOTP API</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">MSG91 Widget ID</label>
                        <input type="text" name="msg91_widget_id" class="form-control" value="{{ $settings['msg91_widget_id'] ?? '' }}" placeholder="Widget ID from OTP Widget/SDK">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">MSG91 Widget Token</label>
                        <input type="text" name="msg91_widget_token" class="form-control" value="{{ old('msg91_widget_token', $settings['msg91_widget_token'] ?? '') }}" placeholder="Token Auth from OTP Widget/SDK">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">MSG91 OTP Template ID</label>
                        <input type="text" name="msg91_otp_template_id" class="form-control" value="{{ $settings['msg91_otp_template_id'] ?? '' }}" placeholder="OTP template ID from MSG91">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">MSG91 Order Confirmation Template ID</label>
                        <input type="text" name="msg91_order_confirmation_template_id" class="form-control" value="{{ $settings['msg91_order_confirmation_template_id'] ?? '' }}" placeholder="SMS flow/template ID">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">MSG91 Delivery Update Template ID</label>
                        <input type="text" name="msg91_delivery_update_template_id" class="form-control" value="{{ $settings['msg91_delivery_update_template_id'] ?? '' }}" placeholder="SMS flow/template ID">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Exotel Settings</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Exotel Account SID</label>
                        <input type="text" name="exotel_sid" class="form-control" value="{{ old('exotel_sid', $settings['exotel_sid'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Exotel API Key</label>
                        <input type="text" name="exotel_api_key" class="form-control" value="{{ old('exotel_api_key', $settings['exotel_api_key'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Exotel API Token</label>
                        <input type="text" name="exotel_api_token" class="form-control" value="{{ old('exotel_api_token', $settings['exotel_api_token'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Exotel Subdomain</label>
                        <input type="text" name="exotel_subdomain" class="form-control" value="{{ $settings['exotel_subdomain'] ?? 'api.exotel.com' }}" placeholder="api.exotel.com or api.in.exotel.com">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Exotel Sender ID</label>
                        <input type="text" name="exotel_sender_id" class="form-control" value="{{ $settings['exotel_sender_id'] ?? '' }}" placeholder="SMS sender ID / ExoPhone">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Exotel Webhook Secret</label>
                        <input type="text" name="exotel_webhook_secret" class="form-control" value="{{ old('exotel_webhook_secret', $settings['exotel_webhook_secret'] ?? '') }}" placeholder="Shared secret appended to the status callback URL">
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Exotel Status Callback URL</label>
                        <input type="text" class="form-control" value="{{ route('webhooks.exotel.call-status') }}" readonly>
                        <small class="text-muted">Paste this into your Exotel Call flow / app configuration.</small>
                    </div>
                </div>

                <div class="settings-section-title mt-4">Order Acceptance Alert Calls</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Enable alert calls</label>
                        <select name="exotel_order_alert_calls_enabled" class="form-select">
                            <option value="0" {{ ($settings['exotel_order_alert_calls_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Off</option>
                            <option value="1" {{ ($settings['exotel_order_alert_calls_enabled'] ?? '0') == '1' ? 'selected' : '' }}>On</option>
                        </select>
                        <small class="text-muted">Rings the driver / restaurant when an order is not accepted in time. Requires Exotel to be configured above.</small>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Trigger after (seconds)</label>
                        <input type="number" name="exotel_order_alert_delay_seconds" class="form-control" min="5" max="120" value="{{ $settings['exotel_order_alert_delay_seconds'] ?? 10 }}">
                        <small class="text-muted">Minimum unaccepted age before a call. The scan runs every minute, so the real trigger is 10&ndash;70s.</small>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Caller ID (ExoPhone)</label>
                        <input type="text" name="exotel_order_alert_caller_id" class="form-control" value="{{ $settings['exotel_order_alert_caller_id'] ?? '' }}" placeholder="Defaults to the Sender ID above">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Announcement flow URL</label>
                        <input type="text" name="exotel_order_alert_flow_url" class="form-control" value="{{ $settings['exotel_order_alert_flow_url'] ?? '' }}" placeholder="https://my.exotel.com/…/exoml/start_voice/NNNNN">
                        <small class="text-muted">Exotel App / flow that speaks the alert when the person answers.</small>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">…or bridge to number</label>
                        <input type="text" name="exotel_order_alert_number" class="form-control" value="{{ $settings['exotel_order_alert_number'] ?? '' }}" placeholder="Used only when no flow URL is set">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Call Masking</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Phone Number Mode</label>
                        <select name="phone_masking_mode" class="form-select">
                            <option value="raw" {{ ($settings['phone_masking_mode'] ?? 'raw') == 'raw' ? 'selected' : '' }}>Raw (show real numbers)</option>
                            <option value="exotel" {{ ($settings['phone_masking_mode'] ?? 'raw') == 'exotel' ? 'selected' : '' }}>Exotel (masked numbers)</option>
                        </select>
                        <small class="text-muted">Manage the pool of masking numbers under Call Masking Pool.</small>
                    </div>
                </div>

                <div class="settings-section-title mt-4">Social Login</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Social Login</label>
                        <select name="social_login_enabled" class="form-select">
                            <option value="1" {{ ($settings['social_login_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ ($settings['social_login_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Google Login</label>
                        <select name="social_login_google_enabled" class="form-select">
                            <option value="1" {{ ($settings['social_login_google_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ ($settings['social_login_google_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Apple Login</label>
                        <select name="social_login_apple_enabled" class="form-select">
                            <option value="1" {{ ($settings['social_login_apple_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ ($settings['social_login_apple_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Auto Register</label>
                        <select name="social_login_auto_register" class="form-select">
                            <option value="1" {{ ($settings['social_login_auto_register'] ?? '1') == '1' ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ ($settings['social_login_auto_register'] ?? '1') == '0' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Google Web Client ID</label>
                        <input type="text" name="social_login_google_web_client_id" class="form-control" value="{{ $settings['social_login_google_web_client_id'] ?? '' }}" placeholder="Firebase OAuth web client ID">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Apple Services ID</label>
                        <input type="text" name="social_login_apple_services_id" class="form-control" value="{{ $settings['social_login_apple_services_id'] ?? '' }}" placeholder="com.example.app.service">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Auto-link Verified Email</label>
                        <select name="social_login_auto_link_verified_email" class="form-select">
                            <option value="1" {{ ($settings['social_login_auto_link_verified_email'] ?? '1') == '1' ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ ($settings['social_login_auto_link_verified_email'] ?? '1') == '0' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Communication Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection



