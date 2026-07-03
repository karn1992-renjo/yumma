@extends('layouts.admin')

@section('title', 'Gift Cards')
@section('header', 'Gift Cards')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Gift Cards</h1>
            <p>Create wallet gift cards customers can redeem in the app, then track wallet credits as they happen.</p>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="table-card p-3 h-100">
            <div class="small text-muted">Active Cards</div>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['active'] ?? 0) }}</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-3 h-100">
            <div class="small text-muted">Cards Created</div>
            <h3 class="mb-0 fw-bold">{{ number_format($giftCards->total()) }}</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-3 h-100">
            <div class="small text-muted">Total Redemptions</div>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['total_redemptions'] ?? 0) }}</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-3 h-100">
            <div class="small text-muted">Wallet Value Redeemed</div>
            <h3 class="mb-0 fw-bold">{{ $currencySymbol ?? 'INR ' }}{{ number_format($stats['redeemed_value'] ?? 0, App\Models\AppSetting::currencyDecimals()) }}</h3>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold"><i class="fas fa-gift me-2"></i>Create Gift Card</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.gift-cards.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Code</label>
                        <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code') }}" placeholder="Auto generated if empty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="Campaign name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount</label>
                        <input type="number" name="amount" class="form-control" step="{{ $currencyStep ?? '0.01' }}" min="1" value="{{ old('amount') }}" required>
                        <small class="text-muted">This amount is credited to the customer wallet on redemption.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Maximum Redemptions</label>
                        <input type="number" name="max_redemptions" class="form-control" min="1" value="{{ old('max_redemptions') }}" placeholder="Unlimited if empty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expires At</label>
                        <input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Create Gift Card</button>
                </form>
            </div>
        </div>

        <div class="table-card mt-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold"><i class="fas fa-clock-rotate-left me-2"></i>Recent Redemptions</h5>
            </div>
            <div class="list-group list-group-flush">
                @forelse($recentRedemptions as $redemption)
                    <div class="list-group-item px-4 py-3">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-bold">{{ $redemption->giftCard?->code ?? 'Gift card removed' }}</div>
                                <div class="small text-muted">
                                    {{ $redemption->user?->name ?? 'User removed' }}
                                    @if($redemption->user?->email)
                                        <span class="d-block">{{ $redemption->user->email }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">{{ $currencySymbol ?? 'INR ' }}{{ number_format($redemption->amount, App\Models\AppSetting::currencyDecimals()) }}</div>
                                <div class="small text-muted">{{ $redemption->redeemed_at?->format('d M, h:i A') }}</div>
                            </div>
                        </div>
                        @if($redemption->walletTransaction)
                            <div class="small text-muted mt-2">
                                Wallet balance after: {{ $currencySymbol ?? 'INR ' }}{{ number_format($redemption->walletTransaction->balance_after, App\Models\AppSetting::currencyDecimals()) }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">No gift cards have been redeemed yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <form method="GET" class="d-flex gap-2">
                    <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search gift cards">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                    @if(request('search'))
                        <a href="{{ route('admin.gift-cards.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Code</th>
                            <th>Amount</th>
                            <th>Redemptions</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($giftCards as $giftCard)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $giftCard->code }}</div>
                                    <div class="small text-muted">{{ $giftCard->title ?: 'Untitled' }}</div>
                                </td>
                                <td>{{ $currencySymbol ?? 'INR ' }}{{ number_format($giftCard->amount, App\Models\AppSetting::currencyDecimals()) }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $giftCard->redeemed_count }}{{ $giftCard->max_redemptions ? ' / ' . $giftCard->max_redemptions : '' }}</div>
                                    <div class="small text-muted">{{ $currencySymbol ?? 'INR ' }}{{ number_format($giftCard->redemptions_count * $giftCard->amount, App\Models\AppSetting::currencyDecimals()) }} credited</div>
                                </td>
                                <td>{{ $giftCard->expires_at ? $giftCard->expires_at->format('d M Y, h:i A') : 'Never' }}</td>
                                <td>
                                    <span class="badge {{ $giftCard->is_redeemable ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $giftCard->is_redeemable ? 'Redeemable' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editGiftCard{{ $giftCard->id }}">Edit</button>
                                    <form action="{{ route('admin.gift-cards.destroy', $giftCard) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this gift card?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" {{ $giftCard->redeemed_count > 0 ? 'disabled' : '' }}>Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <tr class="collapse" id="editGiftCard{{ $giftCard->id }}">
                                <td colspan="6" class="bg-light">
                                    <form action="{{ route('admin.gift-cards.update', $giftCard) }}" method="POST" class="row g-3 align-items-end">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold">Title</label>
                                            <input type="text" name="title" class="form-control" value="{{ $giftCard->title }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold">Amount</label>
                                            <input type="number" name="amount" class="form-control" step="{{ $currencyStep ?? '0.01' }}" value="{{ $giftCard->amount }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold">Max Uses</label>
                                            <input type="number" name="max_redemptions" class="form-control" value="{{ $giftCard->max_redemptions }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold">Expires At</label>
                                            <input type="datetime-local" name="expires_at" class="form-control" value="{{ $giftCard->expires_at ? $giftCard->expires_at->format('Y-m-d\TH:i') : '' }}">
                                        </div>
                                        <div class="col-md-1">
                                            <input type="hidden" name="is_active" value="0">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $giftCard->is_active ? 'checked' : '' }}>
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary w-100">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No gift cards found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $giftCards->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
