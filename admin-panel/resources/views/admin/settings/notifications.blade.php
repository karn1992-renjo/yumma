@extends('layouts.admin')

@section('title', 'Notification Settings')
@section('header', 'Notification Settings')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Notification Settings</h1>
            <p>Configure SMS and push notification providers.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h5 class="mb-1 fw-bold">Firebase Push Health</h5>
                        <p class="text-muted mb-0">These values are what the admin panel will actually use for order pushes and custom broadcasts across customer, restaurant, and driver apps.</p>
                    </div>
                    <span class="badge {{ !empty($firebaseDiagnostics['configured']) ? 'bg-success' : 'bg-danger' }} fs-6 px-3 py-2">
                        {{ !empty($firebaseDiagnostics['configured']) ? 'Connected' : 'Not Ready' }}
                    </span>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted mb-1">Firebase Enabled</div>
                            <div class="fw-semibold">{{ !empty($firebaseDiagnostics['enabled']) ? 'Yes' : 'No' }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted mb-1">Project ID</div>
                            <div class="fw-semibold">{{ $firebaseDiagnostics['project_id'] ?: 'Not set' }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted mb-1">Service Account</div>
                            <div class="fw-semibold">{{ !empty($firebaseDiagnostics['service_account_exists']) ? 'Uploaded' : 'Missing' }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted mb-1">Failure Reason</div>
                            <div class="fw-semibold">
                                {{ !empty($firebaseDiagnostics['configured']) ? 'None' : ($firebaseDiagnostics['failure_reason'] ?: 'None') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="table-card">
            <div class="p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-1 fw-bold">Admin Push Broadcasts</h5>
                    <p class="text-muted mb-0">Create live push notifications for all users or selected roles with delivery history.</p>
                </div>
                <a href="{{ route('admin.push-notifications.index') }}" class="btn btn-primary rounded-3">
                    <i class="fas fa-paper-plane me-2"></i> Open Push Notifications
                </a>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="table-card">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <h5 class="mb-1 fw-bold">Send Test Push</h5>
                        <p class="text-muted mb-0">Send a live test notification to one real app user by app role and mobile number.</p>
                    </div>
                    <span class="badge bg-light text-dark border fs-6 px-3 py-2">Customer / Restaurant / Driver</span>
                </div>

                <form action="{{ route('admin.settings.notifications.test-push') }}" method="POST">
                    @csrf
                    <div class="row gy-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Target App</label>
                            <select name="target_app" class="form-select" required>
                                <option value="customer" {{ old('target_app') === 'customer' ? 'selected' : '' }}>Customer App</option>
                                <option value="restaurant" {{ old('target_app') === 'restaurant' ? 'selected' : '' }}>Restaurant App</option>
                                <option value="driver" {{ old('target_app') === 'driver' ? 'selected' : '' }}>Driver App</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                value="{{ old('phone') }}"
                                placeholder="{{ $settings['default_mobile_country_code'] ?? '+91' }}9876543210"
                                required
                            >
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Title</label>
                            <input
                                type="text"
                                name="title"
                                class="form-control"
                                value="{{ old('title', 'FoodFlow test push') }}"
                                maxlength="120"
                                required
                            >
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Deep Link</label>
                            <input
                                type="text"
                                name="deep_link"
                                class="form-control"
                                value="{{ old('deep_link', '/support') }}"
                                placeholder="/support"
                            >
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-semibold">Message</label>
                            <textarea
                                name="body"
                                class="form-control"
                                rows="3"
                                maxlength="500"
                                required
                            >{{ old('body', 'This is a live push test from admin settings.') }}</textarea>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-bell me-2"></i> Send Test Push
                            </button>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Use the same mobile number that is logged in on the target app. The user must have opened the app at least once so an FCM token is saved.</small>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Notification Provider Settings</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="redirect_to" value="admin.settings.notifications">

                    <div class="row gy-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Twilio Account SID</label>
                            <input type="text" name="twilio_account_sid" class="form-control" value="{{ $settings['twilio_account_sid'] ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Twilio Auth Token</label>
                            <input type="text" name="twilio_auth_token" class="form-control" value="{{ old('twilio_auth_token', $settings['twilio_auth_token'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Twilio Phone Number</label>
                            <input type="text" name="twilio_phone_number" class="form-control" value="{{ $settings['twilio_phone_number'] ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Twilio Call Service</label>
                            <select name="twilio_call_enabled" class="form-select">
                                <option value="1" {{ ($settings['twilio_call_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ($settings['twilio_call_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <h5 class="mb-3 fw-semibold">Pusher Realtime Settings</h5>
                    <div class="alert alert-info rounded-3">
                        <div class="fw-semibold mb-1">Required for realtime order updates</div>
                        <div class="small mb-0">Set Broadcast Connection to Pusher after adding valid Pusher credentials. Saved credentials are shown below for direct editing.</div>
                    </div>
                    <div class="row gy-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Broadcast Connection</label>
                            <select name="broadcast_connection" class="form-select">
                                <option value="null" {{ ($settings['broadcast_connection'] ?? config('broadcasting.default', 'null')) == 'null' ? 'selected' : '' }}>Disabled</option>
                                <option value="log" {{ ($settings['broadcast_connection'] ?? config('broadcasting.default', 'null')) == 'log' ? 'selected' : '' }}>Log Only</option>
                                <option value="pusher" {{ ($settings['broadcast_connection'] ?? config('broadcasting.default', 'null')) == 'pusher' ? 'selected' : '' }}>Pusher</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pusher App ID</label>
                            <input type="text" name="pusher_app_id" class="form-control" value="{{ $settings['pusher_app_id'] ?? config('broadcasting.connections.pusher.app_id', '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pusher App Key</label>
                            <input type="text" name="pusher_app_key" class="form-control" value="{{ $settings['pusher_app_key'] ?? config('broadcasting.connections.pusher.key', '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pusher App Secret</label>
                            <input type="text" name="pusher_app_secret" class="form-control" value="{{ old('pusher_app_secret', $settings['pusher_app_secret'] ?? config('broadcasting.connections.pusher.secret', '')) }}">
                            <div class="form-text">Saved secret is shown here. Edit it directly when needed.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pusher Cluster</label>
                            <input type="text" name="pusher_app_cluster" class="form-control" value="{{ $settings['pusher_app_cluster'] ?? config('broadcasting.connections.pusher.options.cluster', 'mt1') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pusher Scheme</label>
                            <select name="pusher_scheme" class="form-select">
                                <option value="https" {{ ($settings['pusher_scheme'] ?? config('broadcasting.connections.pusher.options.scheme', 'https')) == 'https' ? 'selected' : '' }}>HTTPS</option>
                                <option value="http" {{ ($settings['pusher_scheme'] ?? config('broadcasting.connections.pusher.options.scheme', 'https')) == 'http' ? 'selected' : '' }}>HTTP</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Pusher Host</label>
                            <input type="text" name="pusher_host" class="form-control" value="{{ $settings['pusher_host'] ?? config('broadcasting.connections.pusher.options.host', '') }}" placeholder="api-mt1.pusher.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pusher Port</label>
                            <input type="number" name="pusher_port" class="form-control" value="{{ $settings['pusher_port'] ?? config('broadcasting.connections.pusher.options.port', 443) }}" min="1" max="65535">
                        </div>
                    </div>

                    <hr>

                    <h5 class="mb-3 fw-semibold">Firebase Settings</h5>
                    <div class="alert alert-warning rounded-3">
                        <div class="fw-semibold mb-1">Required for live push delivery</div>
                        <div class="small mb-1">Upload the Firebase Admin SDK service-account JSON here. That is the credential used for order notifications and admin custom push notifications in all apps.</div>
                        <div class="small text-muted">The server key field is kept only for legacy compatibility and is not the primary credential used by the current push service.</div>
                    </div>
                    <div class="row gy-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Firebase Enabled</label>
                            <select name="firebase_enabled" class="form-select">
                                <option value="1" {{ ($settings['firebase_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ($settings['firebase_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Firebase API Key</label>
                            <input type="text" name="firebase_api_key" class="form-control" value="{{ old('firebase_api_key', $settings['firebase_api_key'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Firebase Project ID</label>
                            <input type="text" name="firebase_project_id" class="form-control" value="{{ old('firebase_project_id', $settings['firebase_project_id'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Firebase Database URL</label>
                            <input type="text" name="firebase_database_url" class="form-control" value="{{ old('firebase_database_url', $settings['firebase_database_url'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Firebase Storage Bucket</label>
                            <input type="text" name="firebase_storage_bucket" class="form-control" value="{{ old('firebase_storage_bucket', $settings['firebase_storage_bucket'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Firebase Messaging Sender ID</label>
                            <input type="text" name="firebase_messaging_sender_id" class="form-control" value="{{ old('firebase_messaging_sender_id', $settings['firebase_messaging_sender_id'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Firebase App ID</label>
                            <input type="text" name="firebase_app_id" class="form-control" value="{{ old('firebase_app_id', $settings['firebase_app_id'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Firebase Server Key</label>
                            <input type="text" name="firebase_server_key" class="form-control" value="{{ old('firebase_server_key', $settings['firebase_server_key'] ?? '') }}">
                            <small class="text-muted">Optional legacy field. Live push delivery uses the Admin SDK JSON uploaded below.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Firebase Service Account JSON</label>
                            @if(!empty($firebaseDiagnostics['service_account_exists']))
                                <div class="alert alert-success mb-2 rounded-3">
                                    <div class="fw-semibold">Service account is uploaded</div>
                                    <div class="small">Current file: {{ basename($settings['firebase_service_account_path'] ?? '') }}</div>
                                </div>
                                <input type="file" name="firebase_service_account_json" accept=".json,application/json" class="form-control">
                                <small class="text-muted">Choose a new Firebase Admin SDK JSON only when you want to replace the saved credential.</small>
                            @else
                                <input type="file" name="firebase_service_account_json" accept=".json,application/json" class="form-control">
                                @if(!empty($settings['firebase_service_account_path']))
                                    <small class="text-danger">Saved path exists in settings, but the file is not readable. Upload the JSON again.</small>
                                @endif
                            @endif
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Saved Firebase credential values are visible above for direct review and editing.</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">Save Notification Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
