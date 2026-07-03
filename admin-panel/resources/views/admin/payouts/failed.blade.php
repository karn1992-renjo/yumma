@extends('layouts.admin')

@section('title', 'Failed Payouts')
@section('header', 'Failed Payouts')

@section('content')
<div class="page-header">
    <div>
        <h1>Failed Payouts</h1>
        <p>Retry failures or export them for offline processing.</p>
    </div>
    <a href="{{ route('admin.payouts.export') }}" class="btn btn-outline-primary">Export CSV</a>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Payout</th><th>Gateway</th><th>Error</th><th>Retries</th><th>Next Retry</th><th></th></tr></thead>
            <tbody>
            @forelse($failedPayouts as $failed)
                <tr>
                    <td>#{{ $failed->payout_id }}</td>
                    <td>{{ ucfirst($failed->gateway ?? '-') }}</td>
                    <td>{{ $failed->error_message }}</td>
                    <td>{{ $failed->retry_count }}</td>
                    <td>{{ $failed->next_retry_at?->format('d M Y h:i A') ?? '-' }}</td>
                    <td>
                        @if($failed->payout)
                            <button class="btn btn-sm btn-primary" onclick="retryPayout({{ $failed->payout_id }})">Retry</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">No failed payouts.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $failedPayouts->links() }}</div>
</div>

<script>
function retryPayout(id) {
    fetch(`/admin/payouts/retry/${id}`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json'}
    }).then(response => response.json()).then(() => location.reload());
}
</script>
@endsection
