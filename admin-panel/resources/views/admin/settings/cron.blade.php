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
