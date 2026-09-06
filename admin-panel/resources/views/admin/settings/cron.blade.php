@extends('layouts.admin')

@section('title', 'Cron Job Settings')
@section('header', 'Cron Job Settings')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-clock"></i> Scheduler</span>
            <h1>Cron Job Settings</h1>
            <p>Install the Laravel scheduler and review scheduled work that keeps payouts, notifications, order cleanup, and platform automation moving.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-grid">
        <div class="settings-span-4">
            <div class="settings-card h-100">
                <div class="settings-card-header">
                    <div>
                        <h2 class="settings-card-title">One Click Installer</h2>
                        <p class="settings-card-subtitle">Use this when the server permits scheduler installation from PHP.</p>
                    </div>
                </div>
                <div class="settings-card-body">
                    @if(session('success'))
                        <div class="alert alert-success border-0">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger border-0">{{ session('error') }}</div>
                    @endif

                    <label class="form-label">Scheduler Installation Command</label>
                    <pre class="bg-light border rounded-4 p-3 small mb-3" style="white-space: pre-wrap;">{{ $cronCommand }}</pre>

                    @if(!empty($settings['cron_installed_at']))
                        <div class="alert alert-info border-0">
                            Last installed from panel: {{ $settings['cron_installed_at'] }}
                        </div>
                    @endif

                    <form action="{{ route('admin.settings.cron.install') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary" {{ $canInstallCron ? '' : 'disabled' }}>
                            <i class="fas fa-bolt me-2"></i>Install Cron Job
                        </button>
                    </form>

                    @unless($canInstallCron)
                        <div class="form-text mt-3">Automatic installation requires the PHP proc_open function. Enable it on the server, then reload this page.</div>
                    @endunless
                </div>
            </div>
        </div>


        <div class="settings-span-4">
            <div class="settings-card h-100">
                <div class="settings-card-header">
                    <div>
                        <h2 class="settings-card-title">Restaurant Business Reports</h2>
                        <p class="settings-card-subtitle">Email scheduled performance reports to restaurant owners.</p>
                    </div>
                </div>
                <div class="settings-card-body">
                    <form action="{{ route('admin.settings.business-reports') }}" method="POST">
                        @csrf
                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="restaurant_business_report_enabled" value="0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="restaurant-business-report-enabled"
                                name="restaurant_business_report_enabled"
                                value="1"
                                @checked(($settings['restaurant_business_report_enabled'] ?? '0') == '1')
                            >
                            <label class="form-check-label fw-semibold" for="restaurant-business-report-enabled">Send reports by email</label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <select name="restaurant_business_report_frequency" class="form-select" required>
                                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($settings['restaurant_business_report_frequency'] ?? 'weekly') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Send Time</label>
                            <input type="time" name="restaurant_business_report_time" class="form-control" value="{{ $settings['restaurant_business_report_time'] ?? '08:00' }}" required>
                            <div class="form-text">Uses the server timezone configured for Laravel.</div>
                        </div>

                        <div class="alert alert-light border small mb-3">
                            <div class="fw-semibold mb-1">Report includes:</div>
                            <div>Email summary plus an attached Excel workbook. Sheet 1 is Summary. Sheet 2 is Orders with order number, dates, items, payment/status, subtotal, fees, discount, tax, admin commission, restaurant commission, gateway/GST charges, and final payable amount.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-envelope me-2"></i>Save Report Schedule
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="settings-span-8">
            <div class="settings-card h-100">
                <div class="settings-card-header">
                    <div>
                        <h2 class="settings-card-title">Scheduled Work</h2>
                        <p class="settings-card-subtitle">Enable or disable individual scheduled jobs. Disabled jobs remain installed but are skipped by the scheduler.</p>
                    </div>
                    <span class="badge bg-primary rounded-3">{{ count($scheduledTasks) }} tasks</span>
                </div>
                <form action="{{ route('admin.settings.cron.tasks') }}" method="POST">
                    @csrf
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Task</th>
                                    <th>Type</th>
                                    <th>Frequency</th>
                                    <th>Command</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($scheduledTasks as $task)
                                    <tr>
                                        <td style="min-width: 150px;">
                                            <div class="form-check form-switch">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="cron-task-{{ $task['key'] }}"
                                                    name="enabled_cron_tasks[]"
                                                    value="{{ $task['key'] }}"
                                                    @checked($task['enabled'])
                                                >
                                                <label class="form-check-label fw-semibold" for="cron-task-{{ $task['key'] }}">
                                                    {{ $task['enabled'] ? 'Enabled' : 'Disabled' }}
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $task['name'] }}</div>
                                            <div class="text-muted small">{{ $task['key'] }}</div>
                                        </td>
                                        <td><span class="badge bg-light text-dark">{{ $task['type'] }}</span></td>
                                        <td>
                                            {{ $task['frequency'] }}
                                            @if($task['expression'])
                                                <div class="text-muted small">{{ $task['expression'] }}</div>
                                            @endif
                                        </td>
                                        <td><code class="small">{{ $task['command'] }}</code></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No scheduled tasks found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="settings-action-bar px-3 pb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Cron Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
