@php
    $accountingTabs = [
        ['route' => 'admin.accounting.overview', 'icon' => 'fas fa-gauge', 'label' => 'Overview'],
        ['route' => 'admin.accounting.gst', 'icon' => 'fas fa-file-invoice', 'label' => 'GST'],
        ['route' => 'admin.accounting.tds', 'icon' => 'fas fa-hand-holding-dollar', 'label' => 'TDS'],
        ['route' => 'admin.accounting.tcs', 'icon' => 'fas fa-percent', 'label' => 'TCS'],
        ['route' => 'admin.accounting.settlements', 'icon' => 'fas fa-scale-balanced', 'label' => 'Settlements'],
        ['route' => 'admin.accounting.ledger', 'icon' => 'fas fa-book', 'label' => 'Tax Ledger'],
        ['route' => 'admin.accounting.cess', 'icon' => 'fas fa-hand-holding-heart', 'label' => 'Cess'],
        ['route' => 'admin.accounting.compliance', 'icon' => 'fas fa-clipboard-list', 'label' => 'Compliance'],
        ['route' => 'admin.accounting.gl.balance-sheet', 'icon' => 'fas fa-scale-unbalanced', 'label' => 'Balance Sheet'],
        ['route' => 'admin.accounting.gl.profit-loss', 'icon' => 'fas fa-chart-line', 'label' => 'P&L'],
        ['route' => 'admin.accounting.gl.cash-flow', 'icon' => 'fas fa-money-bill-transfer', 'label' => 'Cash Flow'],
        ['route' => 'admin.accounting.gl.trial-balance', 'icon' => 'fas fa-list-ol', 'label' => 'Trial Balance'],
        ['route' => 'admin.accounting.gl.journals', 'icon' => 'fas fa-file-lines', 'label' => 'Journals'],
        ['route' => 'admin.accounting.gl.chart', 'icon' => 'fas fa-sitemap', 'label' => 'Chart of Accounts'],
        ['route' => 'admin.accounting.documents', 'icon' => 'fas fa-folder-open', 'label' => 'Documents'],
    ];
    $qs = request()->only('from', 'to');
@endphp

<nav class="settings-tabs mb-4" aria-label="Accounting sections">
    @foreach($accountingTabs as $tab)
        <a href="{{ route($tab['route'], $qs) }}" class="settings-tab {{ request()->routeIs($tab['route']) ? 'active' : '' }}">
            <i class="{{ $tab['icon'] }}"></i>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>

@isset($from)
<form method="GET" class="row g-2 align-items-end mb-4">
    <div class="col-sm-3"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
    <div class="col-sm-3"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to ?? '' }}" class="form-control form-control-sm"></div>
    <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
</form>
@endisset
