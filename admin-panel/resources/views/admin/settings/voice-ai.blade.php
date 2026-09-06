@extends('layouts.admin')

@section('title', 'Voice AI Settings')
@section('header', 'Voice AI Settings')

@section('content')
@include('admin.settings._style')

@php
    $providerLabels = [
        'gemini_live' => 'Gemini Live',
        'self_hosted' => 'Self Hosted GPU',
        'none' => 'None',
    ];
    $recentSessions = $health['recent_sessions'] ?? collect();
@endphp

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-microphone-alt"></i> Realtime Ordering</span>
            <div>
                <h1>Voice AI Settings</h1>
                <p>Control the customer voice assistant, provider routing, secure Gemini credentials, self-hosted gateway settings, allowance recharge, and health checks.</p>
            </div>
        </div>
    </div>

    @include('admin.settings._tabs')

    @if(session('success'))
        <div class="alert alert-success rounded-4 border-0 mb-4">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4 border-0 mb-4">
            <strong>There were some problems with your input.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.settings.voice-ai.update') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="table-card mb-4">
                    <div class="card-header bg-transparent"><h5 class="mb-0 fw-bold">General</h5></div>
                    <div class="p-4 row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Voice Assistant</label>
                            <select name="voice_ai_enabled" class="form-select">
                                <option value="1" {{ $settings['voice_ai_enabled'] ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ! $settings['voice_ai_enabled'] ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Primary Provider</label>
                            <select name="voice_ai_primary_provider" class="form-select">
                                <option value="self_hosted" {{ $settings['voice_ai_primary_provider'] === 'self_hosted' ? 'selected' : '' }}>Self Hosted GPU</option>
                                <option value="gemini_live" {{ $settings['voice_ai_primary_provider'] === 'gemini_live' ? 'selected' : '' }}>Gemini Live</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fallback Provider</label>
                            <select name="voice_ai_fallback_provider" class="form-select">
                                <option value="none" {{ $settings['voice_ai_fallback_provider'] === 'none' ? 'selected' : '' }}>None</option>
                                <option value="self_hosted" {{ $settings['voice_ai_fallback_provider'] === 'self_hosted' ? 'selected' : '' }}>Self Hosted GPU</option>
                                <option value="gemini_live" {{ $settings['voice_ai_fallback_provider'] === 'gemini_live' ? 'selected' : '' }}>Gemini Live</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Session Minutes</label>
                            <input type="number" name="voice_ai_max_session_minutes" min="1" max="60" class="form-control" value="{{ $settings['voice_ai_max_session_minutes'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Idle Timeout Seconds</label>
                            <input type="number" name="voice_ai_idle_timeout_seconds" min="10" max="300" class="form-control" value="{{ $settings['voice_ai_idle_timeout_seconds'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Language</label>
                            <select name="voice_ai_language" class="form-select">
                                <option value="auto" {{ $settings['voice_ai_language'] === 'auto' ? 'selected' : '' }}>Auto</option>
                                <option value="en" {{ $settings['voice_ai_language'] === 'en' ? 'selected' : '' }}>English</option>
                                <option value="hi" {{ $settings['voice_ai_language'] === 'hi' ? 'selected' : '' }}>Hindi</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Barge In</label>
                            <select name="voice_ai_barge_in_enabled" class="form-select">
                                <option value="1" {{ $settings['voice_ai_barge_in_enabled'] ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ! $settings['voice_ai_barge_in_enabled'] ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-card mb-4">
                    <div class="card-header bg-transparent"><h5 class="mb-0 fw-bold">Allowance</h5></div>
                    <div class="p-4 row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Allowance Enforcement</label>
                            <select name="voice_ai_allowance_enabled" class="form-select">
                                <option value="1" {{ $settings['voice_ai_allowance_enabled'] ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ! $settings['voice_ai_allowance_enabled'] ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Initial Seconds</label>
                            <input type="number" name="voice_ai_initial_allowance_seconds" min="0" max="86400" class="form-control" value="{{ $settings['voice_ai_initial_allowance_seconds'] }}">
                            <div class="form-text">Default is 600 seconds.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Recharge After Order</label>
                            <select name="voice_ai_recharge_after_order" class="form-select">
                                <option value="1" {{ $settings['voice_ai_recharge_after_order'] ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ! $settings['voice_ai_recharge_after_order'] ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Recharge Seconds</label>
                            <input type="number" name="voice_ai_recharge_seconds" min="0" max="86400" class="form-control" value="{{ $settings['voice_ai_recharge_seconds'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Recharge Mode</label>
                            <select name="voice_ai_recharge_mode" class="form-select">
                                <option value="reset" {{ $settings['voice_ai_recharge_mode'] === 'reset' ? 'selected' : '' }}>Reset to allowance</option>
                                <option value="add" {{ $settings['voice_ai_recharge_mode'] === 'add' ? 'selected' : '' }}>Add to balance</option>
                            </select>
                            <div class="form-text">Reset avoids accumulation by default.</div>
                        </div>
                    </div>
                </div>

                <div class="table-card mb-4">
                    <div class="card-header bg-transparent"><h5 class="mb-0 fw-bold">Gemini Live</h5></div>
                    <div class="p-4 row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gemini API Key</label>
                            <input type="password" name="gemini_api_key" class="form-control" value="" placeholder="{{ $settings['voice_ai_gemini_api_key_configured'] ? 'Stored securely' : 'Not configured' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Model</label>
                            <input type="text" name="voice_ai_gemini_model" class="form-control" value="{{ $settings['voice_ai_gemini_model'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Voice</label>
                            <input type="text" name="voice_ai_gemini_voice" class="form-control" value="{{ $settings['voice_ai_gemini_voice'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Temperature</label>
                            <input type="number" step="0.1" name="voice_ai_gemini_temperature" min="0" max="2" class="form-control" value="{{ $settings['voice_ai_gemini_temperature'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tool Calling</label>
                            <select name="voice_ai_gemini_tool_calling_enabled" class="form-select">
                                <option value="1" {{ $settings['voice_ai_gemini_tool_calling_enabled'] ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ ! $settings['voice_ai_gemini_tool_calling_enabled'] ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">System Prompt</label>
                            <textarea name="voice_ai_gemini_system_prompt" rows="5" class="form-control">{{ $settings['voice_ai_gemini_system_prompt'] }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="table-card">
                    <div class="card-header bg-transparent"><h5 class="mb-0 fw-bold">Self Hosted Gateway</h5></div>
                    <div class="p-4 row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Public Gateway URL</label>
                            <input type="url" name="voice_ai_self_hosted_url" class="form-control" value="{{ $settings['voice_ai_self_hosted_url'] ?: url('/') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Public WebSocket URL</label>
                            <input type="text" name="voice_ai_self_hosted_ws_url" class="form-control" value="{{ $settings['voice_ai_self_hosted_ws_url'] }}" placeholder="wss://swado.online/voice-ai">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gateway Service Secret</label>
                            <input type="password" name="self_hosted_api_secret" class="form-control" value="" placeholder="{{ $settings['voice_ai_self_hosted_api_secret_configured'] ? 'Stored securely' : 'Optional local override' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">LLM Model</label>
                            <input type="text" name="voice_ai_self_hosted_llm_model" class="form-control" value="{{ $settings['voice_ai_self_hosted_llm_model'] }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="table-card mb-4">
                    <div class="card-header bg-transparent"><h5 class="mb-0 fw-bold">Health</h5></div>
                    <div class="p-4">
                        <div class="d-flex justify-content-between mb-3"><span>Service</span><strong>{{ $health['enabled'] ? 'Enabled' : 'Disabled' }}</strong></div>
                        <div class="d-flex justify-content-between mb-3"><span>Primary</span><strong>{{ $providerLabels[$health['primary_provider']] ?? $health['primary_provider'] }}</strong></div>
                        <div class="d-flex justify-content-between mb-3"><span>Fallback</span><strong>{{ $providerLabels[$health['fallback_provider']] ?? $health['fallback_provider'] }}</strong></div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3"><span>Gemini Live</span><strong>{{ $health['gemini_live']['status'] }}</strong></div>
                        <div class="d-flex justify-content-between"><span>Self Hosted</span><strong>{{ $health['self_hosted']['status'] ?? 'unknown' }}</strong></div>
                    </div>
                </div>

                <div class="table-card">
                    <div class="card-header bg-transparent"><h5 class="mb-0 fw-bold">Recent Sessions</h5></div>
                    <div class="p-4">
                        @forelse($recentSessions as $session)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="fw-semibold">{{ $session->provider ?? 'unknown' }} Â· {{ $session->status }}</div>
                                <div class="text-muted small">{{ $session->session_id }} Â· {{ $session->active_seconds }}s</div>
                            </div>
                        @empty
                            <div class="text-muted">No sessions recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Save Voice AI Settings</button>
        </div>
    </form>
</div>
@endsection
