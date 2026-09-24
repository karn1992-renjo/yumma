@extends('layouts.admin')

@section('title', 'Vendor Bank Details')
@section('header', 'Vendor Bank Details')

@section('content')
<div class="page-header">
    <div>
        <h1>{{ $vendorName }} Bank Details</h1>
        <p>Account numbers, IFSC, and UPI IDs are encrypted before storage.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="table-card p-4">
            <h5 class="fw-bold mb-3">Add Bank / UPI</h5>
            <form method="POST" action="{{ route('admin.vendors.bank-details.store', [$vendorType, $vendorId]) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Account Holder Name</label>
                    <input name="account_holder_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Account Number</label>
                    <input name="account_number" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">IFSC Code</label>
                    <input name="ifsc_code" class="form-control" style="text-transform: uppercase">
                    <div class="form-text">IFSC can be validated via bank API before enabling live transfers.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">UPI ID optional</label>
                    <input name="upi_id" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Bank Name</label>
                    <input name="bank_name" class="form-control">
                </div>
                <label class="form-check mb-3">
                    <input type="checkbox" name="is_default" value="1" class="form-check-input" checked>
                    <span class="form-check-label">Set as default payout account</span>
                </label>
                <button class="btn btn-primary w-100">Save Bank Details</button>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="table-card">
            <div class="card-header bg-transparent"><h5 class="mb-0 fw-bold">Saved Accounts</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Holder</th><th>Account</th><th>Bank</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td>{{ $account->account_holder_name }}</td>
                            <td>****{{ $account->account_number_last4 }} @if($account->is_default)<span class="badge bg-primary">Default</span>@endif</td>
                            <td>{{ $account->bank_name ?? '-' }}</td>
                            <td><span class="badge bg-{{ $account->is_verified ? 'success' : 'warning' }}">{{ $account->is_verified ? 'Verified' : 'Pending' }}</span></td>
                            <td><button class="btn btn-sm btn-outline-primary" onclick="testTransfer({{ $account->id }})">Test transfer</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">No bank details added.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function testTransfer(id) {
    fetch(`/admin/vendor-bank-accounts/${id}/test-transfer`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json'}
    }).then(response => response.json()).then(data => {
        alert(data.message || 'Verified');
        location.reload();
    });
}
</script>
@endsection
