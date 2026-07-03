@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Tax & Charges Settings')

@section('content')
<div class="page-header">
    <div>
        <h1>Tax & Additional Charges</h1>
        <p class="text-muted mb-0">Percentage taxes use their taxable base; fixed charges are added directly</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="stat-card">
            <h5 class="mb-4 fw-bold">
                <i class="fas fa-plus-circle me-2 text-primary"></i> Add New Tax/Charge
            </h5>
            
            <form action="{{ route('admin.taxes.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g., GST, Service Charge" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select tax-type-select" required>
                        <option value="gst">GST</option>
                        <option value="service_charge">Service Charge</option>
                        <option value="packaging_charge">Packaging Charge</option>
                        <option value="delivery_charge_tax">Delivery Charge Tax</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Calculation <span class="text-danger">*</span></label>
                    <select name="calculation_type" class="form-select tax-calculation-select" required>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                    <div class="form-text tax-calculation-help">GST and delivery tax are percentage-based.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold tax-rate-label">Rate (%) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="rate" class="form-control tax-rate-input" placeholder="e.g., 5" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Additional information about this charge"></textarea>
                </div>
                <button type="submit" class="btn btn-primary rounded-3 w-100">
                    <i class="fas fa-plus me-2"></i> Add Tax/Charge
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Active Taxes & Charges</h5>
                <span class="badge bg-primary rounded-3">{{ $taxes->count() }} Total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Rate / Amount</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($taxes as $tax)
                        <tr>
                            <td class="fw-semibold">{{ $tax->name }}</td>
                            <td>
                                @if($tax->type == 'gst')
                                    <span class="badge bg-info rounded-3">GST</span>
                                @elseif($tax->type == 'service_charge')
                                    <span class="badge bg-warning rounded-3">Service Charge</span>
                                @elseif($tax->type == 'packaging_charge')
                                    <span class="badge bg-secondary rounded-3">Packaging</span>
                                @else
                                    <span class="badge bg-dark rounded-3">Delivery Tax</span>
                                @endif
                            </td>
                            <td class="fw-bold">
                                @if(($tax->calculation_type ?? 'percentage') === 'fixed')
                                    {{ $currencySymbol }}{{ number_format((float) $tax->rate, App\Models\AppSetting::currencyDecimals()) }}
                                @else
                                    {{ rtrim(rtrim($tax->rate, '0'), '.') }}%
                                @endif
                            </td>
                            <td class="text-muted">{{ Str::limit($tax->description, 30) ?? '-' }}</td>
                            <td>
                                <form action="{{ route('admin.taxes.update', $tax->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="name" value="{{ $tax->name }}">
                                    <input type="hidden" name="type" value="{{ $tax->type }}">
                                    <input type="hidden" name="calculation_type" value="{{ $tax->calculation_type ?? 'percentage' }}">
                                    <input type="hidden" name="rate" value="{{ $tax->rate }}">
                                    <input type="hidden" name="description" value="{{ $tax->description }}">
                                    <input type="hidden" name="is_active" value="{{ $tax->is_active ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-sm rounded-3 {{ $tax->is_active ? 'btn-success' : 'btn-secondary' }}">
                                        {{ $tax->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#editTaxModal{{ $tax->id }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('admin.taxes.destroy', $tax->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" onclick="return confirm('Delete this tax?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editTaxModal{{ $tax->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 rounded-4">
                                    <form action="{{ route('admin.taxes.update', $tax->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header border-0 px-4 pt-4">
                                            <h5 class="modal-title fw-bold">Edit {{ $tax->name }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body px-4">
                                            <div class="mb-3">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="name" class="form-control" value="{{ $tax->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Type</label>
                                                <select name="type" class="form-select tax-type-select" required>
                                                    <option value="gst" {{ $tax->type == 'gst' ? 'selected' : '' }}>GST</option>
                                                    <option value="service_charge" {{ $tax->type == 'service_charge' ? 'selected' : '' }}>Service Charge</option>
                                                    <option value="packaging_charge" {{ $tax->type == 'packaging_charge' ? 'selected' : '' }}>Packaging Charge</option>
                                                    <option value="delivery_charge_tax" {{ $tax->type == 'delivery_charge_tax' ? 'selected' : '' }}>Delivery Charge Tax</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Calculation</label>
                                                <select name="calculation_type" class="form-select tax-calculation-select" required>
                                                    <option value="percentage" @selected(($tax->calculation_type ?? 'percentage') === 'percentage')>Percentage</option>
                                                    <option value="fixed" @selected(($tax->calculation_type ?? 'percentage') === 'fixed')>Fixed Amount</option>
                                                </select>
                                                <div class="form-text tax-calculation-help">GST and delivery tax are percentage-based.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label tax-rate-label">Rate (%)</label>
                                                <input type="number" step="0.01" name="rate" class="form-control tax-rate-input" value="{{ $tax->rate }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea name="description" class="form-control" rows="2">{{ $tax->description }}</textarea>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active" class="form-check-input" id="active{{ $tax->id }}" value="1" {{ $tax->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="active{{ $tax->id }}">Active</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 px-4 pb-4">
                                            <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary rounded-3">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-receipt fa-3x mb-3 d-block opacity-50"></i>
                                    <h5>No Taxes Configured</h5>
                                    <p class="mb-0">Add your first tax using the form.</p>
                                </div>
                             </td>
                         </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tax Calculation Preview -->
<div class="table-card mt-4">
    <div class="card-header">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-calculator me-2 text-primary"></i> Tax Calculation Preview
        </h5>
    </div>
    <div class="p-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Subtotal ({{ $currencySymbol }})</label>
                <input type="number" id="previewSubtotal" class="form-control" value="500" step="50">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Delivery Fee ({{ $currencySymbol }})</label>
                <input type="number" id="previewDeliveryFee" class="form-control" value="40" step="5">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Total Tax ({{ $currencySymbol }})</label>
                <div class="display-4 fw-bold text-primary" id="previewTotalTax">{{ $currencySymbol }}0</div>
            </div>
        </div>
        <hr class="my-4">
        <div id="previewBreakdown" class="row">
            <!-- Breakdown will be shown here -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(form => {
        const typeSelect = form.querySelector('.tax-type-select');
        const calculationSelect = form.querySelector('.tax-calculation-select');
        const rateLabel = form.querySelector('.tax-rate-label');
        const rateInput = form.querySelector('.tax-rate-input');
        const help = form.querySelector('.tax-calculation-help');

        if (!typeSelect || !calculationSelect || !rateLabel || !rateInput) return;

        const syncTaxForm = () => {
            const type = typeSelect.value;
            const percentageOnly = type === 'gst' || type === 'delivery_charge_tax';

            if (percentageOnly) {
                calculationSelect.value = 'percentage';
                help.textContent = 'This type is always calculated as a percentage.';
            } else {
                if (type === 'packaging_charge' && !calculationSelect.dataset.touched) {
                    calculationSelect.value = 'fixed';
                }
                help.textContent = 'Use fixed amount for a flat charge, or percentage for subtotal-based charge.';
            }

            const isFixed = calculationSelect.value === 'fixed';
            rateLabel.innerHTML = `${isFixed ? 'Amount (' + window.currencySymbol + ')' : 'Rate (%)'} <span class="text-danger">*</span>`;
            rateInput.placeholder = isFixed ? 'e.g., 10' : 'e.g., 5';
            rateInput.max = isFixed ? '999.99' : '100';
        };

        calculationSelect.addEventListener('change', () => {
            calculationSelect.dataset.touched = '1';
            syncTaxForm();
        });
        typeSelect.addEventListener('change', syncTaxForm);
        syncTaxForm();
    });

    const subtotalInput = document.getElementById('previewSubtotal');
    const deliveryFeeInput = document.getElementById('previewDeliveryFee');
    const totalTaxSpan = document.getElementById('previewTotalTax');
    const breakdownDiv = document.getElementById('previewBreakdown');
    
    const taxes = @json($taxes->where('is_active', true));
    
    function calculateTax() {
        const subtotal = parseFloat(subtotalInput.value) || 0;
        const deliveryFee = parseFloat(deliveryFeeInput.value) || 0;
        
        let totalTax = 0;
        let breakdownHtml = '<div class="col-12"><h6 class="mb-3">Breakdown:</h6></div>';
        
        taxes.forEach(tax => {
            let taxableAmount = subtotal;
            if (tax.type === 'delivery_charge_tax') {
                taxableAmount = deliveryFee;
            }
            
            const calculationType = tax.calculation_type === 'fixed' ? 'fixed' : 'percentage';
            const taxAmount = calculationType === 'fixed'
                ? Number(tax.rate || 0)
                : taxableAmount * (tax.rate / 100);
            totalTax += taxAmount;
            const rateLabel = calculationType === 'fixed'
                ? `${window.currencySymbol}${Number(tax.rate || 0).toFixed(window.currencyDecimals)}`
                : `${tax.rate}%`;
            
            breakdownHtml += `
                <div class="col-md-3 mb-2">
                    <div class="bg-light rounded-3 p-2">
                        <small class="text-muted">${tax.name} (${rateLabel})</small>
                        <div class="fw-bold">${window.currencySymbol}${taxAmount.toFixed(window.currencyDecimals)}</div>
                    </div>
                </div>
            `;
        });
        
        totalTaxSpan.textContent = window.currencySymbol + totalTax.toFixed(window.currencyDecimals);
        breakdownDiv.innerHTML = breakdownHtml;
    }
    
    subtotalInput.addEventListener('input', calculateTax);
    deliveryFeeInput.addEventListener('input', calculateTax);
    calculateTax();
});
</script>
@endsection
