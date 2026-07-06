@extends('layouts.admin')

@section('title', 'Create Refund Policy')
@section('header', 'Create Refund Policy')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Create Refund Policy</h1>
            <p>Define refund rules and cancellation policies</p>
        </div>
        <a href="{{ route('admin.refund-policies.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Policies
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-10">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Policy Information</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.refund-policies.store') }}" method="POST">
                    @csrf
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Policy Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', 'Standard Refund Policy') }}" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Refund Window (Hours) <span class="text-danger">*</span></label>
                            <input type="number" name="refund_window_hours" class="form-control @error('refund_window_hours') is-invalid @enderror" value="{{ old('refund_window_hours', 2) }}" required>
                            <div class="form-text">Number of hours after order placement during which refund can be requested</div>
                            @error('refund_window_hours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Delivery Charge Refund (%) <span class="text-danger">*</span></label>
                            <input type="number" name="delivery_charge_refund_percentage" step="0.01" class="form-control @error('delivery_charge_refund_percentage') is-invalid @enderror" value="{{ old('delivery_charge_refund_percentage', 100.00) }}" required>
                            <div class="form-text">Percentage of delivery fee to refund on cancellation</div>
                            @error('delivery_charge_refund_percentage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active">Active</option>
                                <option value="inactive" selected>Inactive</option>
                            </select>
                            <div class="form-text">Only one policy can be active at a time</div>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Cancellation Refund Rules</label>
                            <div class="table-responsive">
                                <table class="table table-sm" id="refundRulesTable">
                                    <thead>
                                        <tr>
                                            <th>Order Status</th>
                                            <th>Refund Percentage (%)</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="text" name="cancellation_refund_rules[pending][status]" class="form-control" value="pending" readonly></td>
                                            <td><input type="number" name="cancellation_refund_rules[pending][percentage]" class="form-control" step="0.01" value="95"></td>
                                            <td><button type="button" class="btn btn-sm btn-danger remove-rule" disabled>Remove</button></td>
                                        </tr>
                                        <tr>
                                            <td><input type="text" name="cancellation_refund_rules[confirmed][status]" class="form-control" value="confirmed" readonly></td>
                                            <td><input type="number" name="cancellation_refund_rules[confirmed][percentage]" class="form-control" step="0.01" value="85"></td>
                                            <td><button type="button" class="btn btn-sm btn-danger remove-rule" disabled>Remove</button></td>
                                        </tr>
                                        <tr>
                                            <td><input type="text" name="cancellation_refund_rules[preparing][status]" class="form-control" value="preparing" readonly></td>
                                            <td><input type="number" name="cancellation_refund_rules[preparing][percentage]" class="form-control" step="0.01" value="70"></td>
                                            <td><button type="button" class="btn btn-sm btn-danger remove-rule" disabled>Remove</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="form-text">Define percentage of order total to refund based on cancellation stage</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Policy Content <span class="text-danger">*</span></label>
                            <textarea name="content" class="form-control @error('content') is-invalid @enderror" rows="10" required>{{ old('content', "1. Refund Eligibility\n   - Orders can be cancelled within 2 hours of placement for full refund\n   - Partial refunds may be issued based on order preparation status\n\n2. How to Request a Refund\n   - Go to My Orders section\n   - Select the order you wish to cancel\n   - Click on 'Cancel Order' button\n   - Provide reason for cancellation\n\n3. Processing Time\n   - Refunds are processed within 5-7 business days\n   - Amount will be credited to original payment method\n\n4. Non-Refundable Items\n   - Customized or personalized items\n   - Items that have been prepared and packaged\n\n5. Contact Support\n   - For payment-related issues, contact our support team") }}</textarea>
                            @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Create Policy
                        </button>
                        <a href="{{ route('admin.refund-policies.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Add dynamic rule addition functionality if needed
    document.getElementById('addRuleBtn')?.addEventListener('click', function() {
        const table = document.getElementById('refundRulesTable').getElementsByTagName('tbody')[0];
        const newRow = table.insertRow();
        const statusCell = newRow.insertCell(0);
        const percentageCell = newRow.insertCell(1);
        const actionCell = newRow.insertCell(2);
        
        statusCell.innerHTML = '<input type="text" name="cancellation_refund_rules[new][status]" class="form-control" placeholder="Status name">';
        percentageCell.innerHTML = '<input type="number" name="cancellation_refund_rules[new][percentage]" class="form-control" step="0.01" placeholder="Percentage">';
        actionCell.innerHTML = '<button type="button" class="btn btn-sm btn-danger remove-rule">Remove</button>';
        
        newRow.querySelector('.remove-rule').addEventListener('click', function() {
            newRow.remove();
        });
    });
</script>
@endpush
@endsection
