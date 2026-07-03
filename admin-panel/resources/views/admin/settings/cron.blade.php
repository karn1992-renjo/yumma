@extends('layouts.admin')

@section('title', 'Cron Job Settings')
@section('header', 'Cron Job Settings')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Cron Job Settings</h1>
            <p>Install the Laravel scheduler and review all scheduled work.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="table-card h-100">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">One Click Installer</h5>
            </div>
            <div class="p-4">
                @if(session('success'))
                    <div class="alert alert-success rounded-4 border-0">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger rounded-4 border-0">{{ session('error') }}</div>
                @endif

                <label class="form-label fw-semibold">Scheduler Installation Command</label>
                <pre class="bg-light border rounded-4 p-3 small mb-3" style="white-space: pre-wrap;">{{ $cronCommand }}</pre>

                @if(!empty($settings['cron_installed_at']))
                    <div class="alert alert-info rounded-4 border-0">
                        Last installed from panel: {{ $settings['cron_installed_at'] }}
                    </div>
                @endif

                <form action="{{ route('admin.settings.cron.install') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary rounded-3" {{ $canInstallCron ? '' : 'disabled' }}>
                        <i class="fas fa-bolt me-2"></i> Install Cron Job
                    </button>
                </form>

                @unless($canInstallCron)
                    <div class="form-text mt-3">Automatic installation requires the PHP proc_open function. Enable it on the server, then reload this page.</div>
                @endunless
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="table-card h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Scheduled Work</h5>
                <span class="badge bg-primary rounded-3">{{ count($scheduledTasks) }} tasks</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Type</th>
                            <th>Frequency</th>
                            <th>Command</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scheduledTasks as $task)
                            <tr>
                                <td class="fw-semibold">{{ $task['name'] }}</td>
                                <td><span class="badge bg-light text-dark">{{ $task['type'] }}</span></td>
                                <td>
                                    {{ $task['frequency'] }}
                                    @if($task['expression'])
                                        <div class="text-muted small">{{ $task['expression'] }}</div>
                                    @endif
                                </td>
                                <td><code>{{ $task['command'] }}</code></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No scheduled tasks found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
