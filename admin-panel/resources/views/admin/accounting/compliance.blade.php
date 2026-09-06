@extends('layouts.admin')
@section('title', 'Accounting — Compliance')
@section('header', 'Accounting — Compliance')

@section('content')
@include('admin.settings._style')

@php
    $catLabels = [
        'gst' => 'GST', 'tds' => 'Income-tax TDS', 'income_tax' => 'Income Tax',
        'roc' => 'ROC / Entity', 'labour' => 'Labour / Payroll', 'licence' => 'Licences',
        'cess' => 'Cess', 'other' => 'Other',
    ];
    $statusBadge = [
        'not_applicable' => 'bg-secondary', 'pending' => 'bg-warning text-dark',
        'filed' => 'bg-success', 'overdue' => 'bg-danger',
    ];
@endphp

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <h1>Compliance Register</h1>
            <p>Filtered to what applies to a <strong>{{ str_replace('_', ' ', $entityType) }}</strong> with your current GST / TDS / employee profile. Change the profile in <a href="{{ route('admin.settings.taxation-setup') }}">Taxation Setup</a>.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.accounting.compliance.export') }}"><i class="fas fa-file-excel me-1"></i> Compliance Calendar (Excel)</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.accounting.compliance.export', ['format' => 'pdf']) }}"><i class="fas fa-file-pdf me-1"></i> PDF</a>
        </div>
    </div>
    @include('admin.accounting._tabs')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @forelse($grouped as $cat => $rows)
        <div class="table-card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ $catLabels[$cat] ?? ucfirst($cat) }}</h5></div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Obligation</th><th>Authority</th><th>Frequency</th><th>Due</th><th>Status</th><th>Reference</th><th></th></tr></thead>
                <tbody>
                @foreach($rows as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->name }}
                            @if($item->linked_export && \Illuminate\Support\Facades\Route::has($item->linked_export))
                                <a class="small ms-1" href="{{ route($item->linked_export) }}">open &rarr;</a>
                            @endif
                            @if($item->notes)<div class="small text-muted">{{ $item->notes }}</div>@endif
                        </td>
                        <td class="small text-muted">{{ $item->authority }}</td>
                        <td class="small">{{ ucfirst($item->frequency) }}</td>
                        <td class="small">{{ $item->due_date ? $item->due_date->format('d M Y') : $item->due_rule }}</td>
                        <td><span class="badge {{ $statusBadge[$item->status] ?? 'bg-secondary' }}">{{ ucwords(str_replace('_', ' ', $item->status)) }}</span></td>
                        <td class="small text-muted">{{ $item->reference_no ?: '—' }}{{ $item->period ? ' · ' . $item->period : '' }}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cmp{{ $item->id }}">Update</button>
                        </td>
                    </tr>

                    <div class="modal fade" id="cmp{{ $item->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.accounting.compliance.update') }}">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $item->id }}">
                                    <div class="modal-header"><h5 class="modal-title">{{ $item->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select">
                                                @foreach(['pending','filed','overdue','not_applicable'] as $st)
                                                    <option value="{{ $st }}" @selected($item->status === $st)>{{ ucwords(str_replace('_', ' ', $st)) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6"><label class="form-label small">Period (YYYY-MM)</label><input name="period" class="form-control form-control-sm" value="{{ $item->period }}" placeholder="{{ now()->format('Y-m') }}"></div>
                                            <div class="col-6"><label class="form-label small">Due date</label><input type="date" name="due_date" class="form-control form-control-sm" value="{{ $item->due_date?->toDateString() }}"></div>
                                            <div class="col-6"><label class="form-label small">Filed on</label><input type="date" name="filed_on" class="form-control form-control-sm" value="{{ $item->filed_on?->toDateString() }}"></div>
                                            <div class="col-6"><label class="form-label small">Reference / ARN</label><input name="reference_no" class="form-control form-control-sm" value="{{ $item->reference_no }}"></div>
                                            <div class="col-12"><label class="form-label small">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2">{{ $item->notes }}</textarea></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @empty
        <div class="alert alert-info">No compliance items apply to the current business profile.</div>
    @endforelse
</div>
@endsection
