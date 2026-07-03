@extends('layouts.admin')

@section('title', 'Branch Audit Logs')

@section('content')
<div class="page-header"><h1>Branch Audit Logs</h1><p>User, action, entity, old value, new value, IP address, and date-time tracking.</p></div>
<div class="card"><div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>Date</th><th>Branch</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th>Values</th></tr></thead>
    <tbody>
    @foreach($logs as $log)
        <tr>
            <td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ $log->branch?->name ?? 'Global' }}</td><td>{{ $log->user?->name ?? 'System' }}</td><td>{{ str_replace('.', ' ', $log->action) }}</td><td>{{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</td><td>{{ $log->ip_address }}</td>
            <td><details><summary>View</summary><pre class="small mb-0">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT) }}</pre></details></td>
        </tr>
    @endforeach
    </tbody>
</table></div><div class="card-footer">{{ $logs->links() }}</div></div>
@endsection
