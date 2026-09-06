@extends('layouts.admin')
@section('title', 'Chart of Accounts')
@section('header', 'Chart of Accounts')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Chart of Accounts</h1><p>System accounts are locked. Add your own for bank, capital, expenses, fixed assets.</p></div></div>
    @include('admin.accounting._tabs')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="table-card mb-4">
        <div class="card-header"><h5 class="mb-0">Add account</h5></div>
        <form method="POST" action="{{ route('admin.accounting.gl.chart.store') }}" class="p-3 row g-2 align-items-end">
            @csrf
            <div class="col-sm-2"><label class="form-label small">Code</label><input name="code" class="form-control form-control-sm" required></div>
            <div class="col-sm-4"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" required></div>
            <div class="col-sm-2"><label class="form-label small">Type</label>
                <select name="type" class="form-select form-select-sm">
                    @foreach(['asset','liability','equity','income','expense'] as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-2"><label class="form-label small">Subtype</label><input name="subtype" class="form-control form-control-sm" placeholder="bank / fixed_asset…"></div>
            <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Add</button></div>
        </form>
    </div>

    <div class="table-card">
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Code</th><th>Account</th><th>Type</th><th>Subtype</th><th></th></tr></thead>
            <tbody>
            @foreach($accounts as $a)
                <tr>
                    <td>{{ $a->code }}</td>
                    <td>{{ $a->name }}</td>
                    <td>{{ ucfirst($a->type) }}</td>
                    <td class="text-muted small">{{ $a->subtype }}</td>
                    <td>@if($a->is_system)<span class="badge bg-secondary">system</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
</div>
@endsection
