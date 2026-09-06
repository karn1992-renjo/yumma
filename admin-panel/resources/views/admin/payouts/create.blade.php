@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol(); @endphp

@section('title', 'Create Payout')
@section('header', 'Create New Payout')

@section('content')
<div class="page-header">
    <h1>Create New Payout</h1>
    <p>Create a manual payout for restaurant or driver</p>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Payout Details</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.payouts.store') }}" method="POST" id="manualPayoutForm">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payout Type <span class="text-danger">*</span></label>
                        <select name="type" id="payoutType" class="form-select @error('type') is-invalid @enderror" required onchange="togglePayoutFields()">
                            <option value="">Select Type</option>
                            <option value="restaurant" {{ old('type') == 'restaurant' ? 'selected' : '' }}>Restaurant</option>
                            <option value="driver" {{ old('type') == 'driver' ? 'selected' : '' }}>Driver</option>
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div id="restaurantField" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Restaurant <span class="text-danger">*</span></label>
                            <select name="restaurant_id" id="restaurantSelect" class="form-select @error('restaurant_id') is-invalid @enderror" onchange="loadVendorWallet()">
                                <option value="">Select Restaurant</option>
                                @foreach($restaurants as $restaurant)
                                    <option value="{{ $restaurant->id }}" {{ old('restaurant_id') == $restaurant->id ? 'selected' : '' }}>
                                        {{ $restaurant->name }} ({{ $restaurant->city }})
                                    </option>
                                @endforeach
                            </select>
                            @error('restaurant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div id="driverField" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Driver <span class="text-danger">*</span></label>
                            <select name="driver_id" id="driverSelect" class="form-select @error('driver_id') is-invalid @enderror" onchange="loadVendorWallet()">
                                <option value="">Select Driver</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" {{ old('driver_id') == $driver->id ? 'selected' : '' }}>
                                        {{ $driver->name }} ({{ $driver->phone }})
                                    </option>
                                @endforeach
                            </select>
                            @error('driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div id="walletPanel" class="rounded-4 p-3 mb-3 d-none" style="background: #f8f9ff; border: 1px solid #e6e8ff;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold"><i class="fas fa-wallet me-2 text-primary"></i>Vendor settlement wallet</span>
                            <span id="walletSpinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></span>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-muted small">Available balance (payable now)</div>
                                <div class="fs-4 fw-bold text-success" id="walletBalance">—</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small">Locked / reserved</div>
                                <div class="fs-6 fw-semibold" id="walletLocked">—</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Settled earning not yet paid out</div>
                                <div class="fs-6 fw-semibold" id="walletUnsettled">—</div>
                            </div>
                        </div>
                        <div id="walletMissing" class="alert alert-warning py-2 px-3 small mt-2 mb-0 d-none">
                            <i class="fas fa-exclamation-triangle me-1"></i> This vendor has no settlement wallet yet — nothing can be reserved.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block">Payout amount <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="amount_mode" id="amountModeFull" value="full" onchange="applyAmountMode()">
                            <label class="form-check-label" for="amountModeFull">
                                Full payout — pay the entire available balance (<span id="fullAmountLabel">{{ $currencySymbol }}0.00</span>)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="amount_mode" id="amountModeCustom" value="custom" checked onchange="applyAmountMode()">
                            <label class="form-check-label" for="amountModeCustom">Custom amount</label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text">{{ $currencySymbol }}</span>
                            <input type="number" name="amount" id="amountInput" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" step="0.01" min="1" required>
                        </div>
                        <div class="form-text" id="amountHint">Select a vendor to see the available balance.</div>
                        @error('amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Period Start <span class="text-danger">*</span></label>
                            <input type="date" name="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start') }}" required>
                            @error('period_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Period End <span class="text-danger">*</span></label>
                            <input type="date" name="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end') }}" required>
                            @error('period_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-2"></i> Create Payout
                        </button>
                        <a href="{{ route('admin.payouts.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const WALLET_URL = "{{ route('admin.payouts.vendor-wallet') }}";
    const CURRENCY = @json($currencySymbol);
    let walletState = { balance: 0, found: false };

    function fmt(v) {
        return CURRENCY + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function togglePayoutFields() {
        const type = document.getElementById('payoutType').value;
        document.getElementById('restaurantField').style.display = type === 'restaurant' ? 'block' : 'none';
        document.getElementById('driverField').style.display = type === 'driver' ? 'block' : 'none';
        loadVendorWallet();
    }

    function selectedVendor() {
        const type = document.getElementById('payoutType').value;
        if (type === 'restaurant') return { type, id: document.getElementById('restaurantSelect').value };
        if (type === 'driver') return { type, id: document.getElementById('driverSelect').value };
        return { type: '', id: '' };
    }

    function loadVendorWallet() {
        const { type, id } = selectedVendor();
        const panel = document.getElementById('walletPanel');
        if (!type || !id) {
            panel.classList.add('d-none');
            walletState = { balance: 0, found: false };
            document.getElementById('fullAmountLabel').textContent = fmt(0);
            document.getElementById('amountHint').textContent = 'Select a vendor to see the available balance.';
            return;
        }

        panel.classList.remove('d-none');
        document.getElementById('walletSpinner').classList.remove('d-none');
        document.getElementById('walletMissing').classList.add('d-none');

        fetch(`${WALLET_URL}?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                document.getElementById('walletSpinner').classList.add('d-none');
                if (!data.success) throw new Error('lookup failed');

                walletState = { balance: Number(data.wallet_balance || 0), found: !!data.wallet_found };
                document.getElementById('walletBalance').textContent = fmt(data.wallet_balance);
                document.getElementById('walletLocked').textContent = fmt(data.wallet_locked);
                document.getElementById('walletUnsettled').textContent = fmt(data.unsettled_earning);
                document.getElementById('fullAmountLabel').textContent = fmt(data.wallet_balance);
                document.getElementById('walletMissing').classList.toggle('d-none', !!data.wallet_found);
                applyAmountMode();
            })
            .catch(() => {
                document.getElementById('walletSpinner').classList.add('d-none');
                document.getElementById('amountHint').textContent = 'Could not load the vendor wallet balance.';
            });
    }

    function applyAmountMode() {
        const mode = document.querySelector('input[name="amount_mode"]:checked').value;
        const input = document.getElementById('amountInput');
        const hint = document.getElementById('amountHint');
        const submitBtn = document.getElementById('submitBtn');

        if (mode === 'full') {
            input.value = walletState.balance.toFixed(2);
            input.readOnly = true;
            input.classList.add('bg-light');
        } else {
            input.readOnly = false;
            input.classList.remove('bg-light');
        }

        const amt = parseFloat(input.value || '0');
        if (walletState.found && amt > walletState.balance + 0.005) {
            hint.innerHTML = `<span class="text-danger">Exceeds available balance (${fmt(walletState.balance)}). The payout will be rejected.</span>`;
            submitBtn.disabled = true;
        } else if (walletState.found) {
            hint.innerHTML = `Available balance: <strong>${fmt(walletState.balance)}</strong>. This amount is reserved from the wallet on create.`;
            submitBtn.disabled = false;
        } else {
            hint.textContent = 'Select a vendor to see the available balance.';
            submitBtn.disabled = false;
        }
    }

    document.getElementById('amountInput').addEventListener('input', applyAmountMode);

    // Initialize on page load
    togglePayoutFields();
    applyAmountMode();
</script>
@endsection
