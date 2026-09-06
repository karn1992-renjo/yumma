@extends('layouts.admin')

@section('title', 'AI Settings')
@section('header', 'AI Settings')

@section('styles')
@include('admin.ai._partials.styles')
<style>
    .ai-form-label { font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 6px; }
    .ai-form-control, .ai-form-select { border-radius: 10px; border: 1px solid #e2e8f0; padding: 9px 12px; font-weight: 600; font-size: 13.5px; }
</style>
@endsection

@section('content')
<div class="ai-shell">
    <div class="ai-hero">
        <div>
            <h1>AI Settings</h1>
            <p>AI starts in monitor and simulation mode. Autonomous mode is intentionally blocked.</p>
        </div>
        <a href="{{ route('admin.ai.index') }}" class="ai-btn ai-btn-outline">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.ai.settings.update') }}" method="POST" class="ai-grid" style="grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); align-items: start;">
        @csrf
        <div class="ai-grid">
            <div class="ai-panel">
                <div class="ai-panel-head"><h3 class="ai-panel-title">Runtime</h3></div>
                <div class="ai-panel-body row g-3">
                    <div class="col-md-4">
                        <div class="ai-form-label">AI Enabled</div>
                        <select name="ai_enabled" class="form-select ai-form-select">
                            <option value="0" {{ ! $settings['ai_enabled'] ? 'selected' : '' }}>Disabled</option>
                            <option value="1" {{ $settings['ai_enabled'] ? 'selected' : '' }}>Enabled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Autonomy Mode</div>
                        <select name="ai_autonomy_mode" class="form-select ai-form-select">
                            @foreach(['monitor', 'assist', 'auto'] as $mode)
                                <option value="{{ $mode }}" {{ $settings['ai_autonomy_mode'] === $mode ? 'selected' : '' }}>{{ ucfirst($mode) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Simulation Mode</div>
                        <select name="ai_simulation_mode" class="form-select ai-form-select">
                            <option value="1" {{ $settings['ai_simulation_mode'] ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ ! $settings['ai_simulation_mode'] ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Low-risk Auto</div>
                        <select name="ai_low_risk_auto_enabled" class="form-select ai-form-select">
                            <option value="0" {{ ! $settings['ai_low_risk_auto_enabled'] ? 'selected' : '' }}>Disabled</option>
                            <option value="1" {{ $settings['ai_low_risk_auto_enabled'] ? 'selected' : '' }}>Enabled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">AI Notifications Need Approval</div>
                        <select name="ai_notifications_require_approval" class="form-select ai-form-select">
                            <option value="0" {{ ! ($settings['ai_notifications_require_approval'] ?? false) ? 'selected' : '' }}>No — send automatically</option>
                            <option value="1" {{ ($settings['ai_notifications_require_approval'] ?? false) ? 'selected' : '' }}>Yes — queue for review</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Auto-execute All Actions</div>
                        <select name="ai_auto_execute" class="form-select ai-form-select">
                            <option value="0" {{ ! ($settings['ai_auto_execute'] ?? false) ? 'selected' : '' }}>No — use approval queue</option>
                            <option value="1" {{ ($settings['ai_auto_execute'] ?? false) ? 'selected' : '' }}>Yes — run every non-blocked action immediately</option>
                        </select>
                        <div class="ai-form-hint">Money/security guardrails still block; nothing gets queued.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Daily Budget USD</div>
                        <input type="number" step="0.01" name="ai_daily_budget_usd" class="form-control ai-form-control" value="{{ $settings['ai_daily_budget_usd'] }}">
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Max Action Amount</div>
                        <input type="number" step="0.01" name="ai_max_action_amount" class="form-control ai-form-control" value="{{ $settings['ai_max_action_amount'] }}">
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Minimum Margin %</div>
                        <input type="number" step="0.01" name="ai_min_margin_percent" class="form-control ai-form-control" value="{{ $settings['ai_min_margin_percent'] }}">
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Max Zone Surge Fee</div>
                        <input type="number" step="0.01" name="ai_max_surge_fee_amount" class="form-control ai-form-control" value="{{ $settings['ai_max_surge_fee_amount'] ?? 30 }}">
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Auto-create Gig Slots from Forecast</div>
                        <select name="ai_gig_autoprovision_enabled" class="form-select ai-form-select">
                            <option value="0" {{ ! ($settings['ai_gig_autoprovision_enabled'] ?? false) ? 'selected' : '' }}>No — only propose in approvals</option>
                            <option value="1" {{ ($settings['ai_gig_autoprovision_enabled'] ?? false) ? 'selected' : '' }}>Yes — publish slots when demand is forecast to outrun capacity</option>
                        </select>
                        <div class="ai-form-hint">Runs hourly. Clones pay/thresholds from your most recent gig in that area.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Gig Forecast Horizon (hours)</div>
                        <input type="number" min="1" max="72" name="ai_gig_autoprovision_horizon_hours" class="form-control ai-form-control" value="{{ $settings['ai_gig_autoprovision_horizon_hours'] ?? 24 }}">
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Max Gig Slots per Run</div>
                        <input type="number" min="1" max="20" name="ai_gig_autoprovision_max_slots_per_run" class="form-control ai-form-control" value="{{ $settings['ai_gig_autoprovision_max_slots_per_run'] ?? 3 }}">
                    </div>
                    <div class="col-md-4">
                        <div class="ai-form-label">Min Forecast Orders to Act</div>
                        <input type="number" min="1" max="500" name="ai_gig_autoprovision_min_forecast_orders" class="form-control ai-form-control" value="{{ $settings['ai_gig_autoprovision_min_forecast_orders'] ?? 5 }}">
                    </div>
                </div>
            </div>

            <div class="ai-panel">
                <div class="ai-panel-head">
                    <h3 class="ai-panel-title">Providers</h3>
                    <button type="submit" formaction="{{ route('admin.ai.settings.test-connection') }}" formnovalidate class="ai-btn ai-btn-outline">
                        <i class="fas fa-plug me-1"></i>Test connection
                    </button>
                </div>
                @if($connectionTest)
                    <div class="ai-panel-body pb-0">
                        <div class="text-muted small mb-2">Live connectivity check ({{ now()->format('H:i:s') }})</div>
                        <div class="row g-2 mb-3">
                            @foreach($connectionTest as $result)
                                <div class="col-md-6">
                                    <div class="ai-row-card" style="align-items: flex-start;">
                                        <div class="ai-row-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="ai-row-title">{{ ucfirst($result['provider']) }}</span>
                                                <span class="ai-status-pill {{ $result['success'] ? 'low' : 'high' }}">{{ $result['success'] ? 'Connected' : $result['status'] }}</span>
                                            </div>
                                            <div class="ai-row-meta text-wrap">{{ $result['message'] }}</div>
                                            @isset($result['latency_ms'])
                                                <div class="ai-row-meta">{{ $result['latency_ms'] }} ms</div>
                                            @endisset
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="ai-panel-body row g-3">
                    <div class="col-md-6">
                        <div class="ai-form-label">Primary Provider</div>
                        <select name="ai_provider" class="form-select ai-form-select">
                            <option value="gemini" {{ $settings['ai_provider'] === 'gemini' ? 'selected' : '' }}>Gemini</option>
                            <option value="openai" {{ $settings['ai_provider'] === 'openai' ? 'selected' : '' }}>OpenAI</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="ai-form-label">Fallback Provider</div>
                        <select name="ai_fallback_provider" class="form-select ai-form-select">
                            <option value="openai" {{ $settings['ai_fallback_provider'] === 'openai' ? 'selected' : '' }}>OpenAI</option>
                            <option value="gemini" {{ $settings['ai_fallback_provider'] === 'gemini' ? 'selected' : '' }}>Gemini</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="ai-form-label">Gemini Model</div>
                        <input name="gemini_model" class="form-control ai-form-control" value="{{ $settings['gemini_model'] }}">
                    </div>
                    <div class="col-md-6">
                        <div class="ai-form-label">OpenAI Model</div>
                        <input name="openai_model" class="form-control ai-form-control" value="{{ $settings['openai_model'] }}">
                    </div>
                    <div class="col-md-6">
                        <div class="ai-form-label">Gemini API Key</div>
                        <input type="password" name="gemini_api_key" class="form-control ai-form-control" placeholder="{{ $health['gemini_configured'] ? 'Stored securely' : 'Not configured' }}">
                    </div>
                    <div class="col-md-6">
                        <div class="ai-form-label">OpenAI API Key</div>
                        <input type="password" name="openai_api_key" class="form-control ai-form-control" placeholder="{{ $health['openai_configured'] ? 'Stored securely' : 'Not configured' }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="ai-grid">
            <div class="ai-panel">
                <div class="ai-panel-head"><h3 class="ai-panel-title">Guardrail status</h3></div>
                <div class="ai-panel-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-semibold small">Kill switch</span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="ai-status-pill {{ $health['kill_switch'] ? 'high' : 'low' }}">{{ $health['kill_switch'] ? 'Active' : 'Clear' }}</span>
                            @if($health['kill_switch'])
                                <form action="{{ route('admin.ai.resume') }}" method="POST" onsubmit="return confirm('Resume AI automatic execution?')">
                                    @csrf
                                    <button class="ai-btn ai-btn-success" style="padding: 4px 10px; font-size: 11px;">Resume</button>
                                </form>
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-semibold small">Simulation</span>
                        <span class="ai-status-pill {{ $health['simulation_mode'] ? 'medium' : 'low' }}">{{ $health['simulation_mode'] ? 'On' : 'Off' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold small">Provider</span>
                        <span class="text-muted small">{{ ucfirst($health['provider']) }}</span>
                    </div>
                </div>
            </div>
            <div class="ai-panel">
                <div class="ai-panel-head"><h3 class="ai-panel-title">Registered tools</h3></div>
                <div class="ai-panel-body">
                    <div class="ai-row-list">
                        @foreach($tools as $key => $tool)
                            <div class="ai-row-card">
                                <div class="ai-row-body"><span class="ai-row-title" style="font-size: 12px;">{{ $key }}</span></div>
                                <span class="ai-status-pill {{ $tool['type'] === 'read' ? 'info' : $tool['risk'] }}">{{ $tool['type'] === 'read' ? 'read' : $tool['risk'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <button class="ai-btn ai-btn-primary w-100" style="padding: 12px;">Save settings</button>
        </div>
    </form>
</div>
@endsection
