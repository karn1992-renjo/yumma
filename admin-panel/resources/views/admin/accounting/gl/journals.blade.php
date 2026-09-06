@extends('layouts.admin')
@section('title', 'Journals')
@section('header', 'Journals')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Journals</h1><p>Auto-posted entries plus any manual journals (bank, capital, salaries, rent, depreciation…).</p></div></div>
    @include('admin.accounting._tabs')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="table-card mb-4">
        <div class="card-header"><h5 class="mb-0">New manual journal</h5></div>
        <form method="POST" action="{{ route('admin.accounting.gl.journals.store') }}" class="p-3" id="jform">
            @csrf
            <div class="row g-2 mb-2">
                <div class="col-sm-3"><input type="date" name="date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                <div class="col-sm-6"><input name="narration" class="form-control form-control-sm" placeholder="Narration" required></div>
            </div>
            <table class="table table-sm mb-2" id="jlines">
                @for($i = 0; $i < 4; $i++)
                <tr>
                    <td><select name="lines[{{ $i }}][account_id]" class="form-select form-select-sm">
                        <option value="">— account —</option>
                        @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach
                    </select></td>
                    <td style="width:130px"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" class="form-control form-control-sm" placeholder="Debit"></td>
                    <td style="width:130px"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" class="form-control form-control-sm" placeholder="Credit"></td>
                    <td><input name="lines[{{ $i }}][memo]" class="form-control form-control-sm" placeholder="Memo"></td>
                </tr>
                @endfor
            </table>
            <button class="btn btn-sm btn-primary">Post journal</button>
            <span class="text-muted small ms-2">Total debit must equal total credit.</span>
        </form>
    </div>

    <div class="table-card">
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Entry</th><th>Date</th><th>Narration</th><th>Source</th><th class="text-end">Debit</th><th></th></tr></thead>
            <tbody>
            @forelse($entries as $e)
                <tr class="{{ $e->status === 'void' ? 'text-muted' : '' }}">
                    <td>{{ $e->entry_no }}</td>
                    <td>{{ $e->date->toDateString() }}</td>
                    <td>{{ $e->narration }}<div class="small text-muted">
                        @foreach($e->lines as $l){{ $l->account->code }} {{ $l->debit > 0 ? 'Dr '.$m($l->debit) : 'Cr '.$m($l->credit) }}@if(!$loop->last); @endif @endforeach
                    </div></td>
                    <td class="small">{{ $e->is_manual ? 'Manual' : ($e->kind ?: '—') }}</td>
                    <td class="text-end">{{ $m($e->lines->sum('debit')) }}</td>
                    <td>@if($e->is_manual && $e->status !== 'void')
                        <form method="POST" action="{{ route('admin.accounting.gl.journals.void', $e) }}" onsubmit="return confirm('Void this journal?')">@csrf<button class="btn btn-sm btn-outline-danger">Void</button></form>
                    @elseif($e->status === 'void')<span class="badge bg-danger">void</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No journals yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
