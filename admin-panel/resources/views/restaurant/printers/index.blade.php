@extends('layouts.restaurant')

@section('title', 'Printer Settings')

@section('styles')
<style>
    .printer-shell {
        padding: 1.25rem;
    }

    .printer-header {
        background: linear-gradient(135deg, #111827 0%, #4f116e 54%, #f97316 100%);
        border-radius: 18px;
        color: #fff;
        padding: 1.4rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.16);
    }

    .printer-header h1 {
        font-size: 1.75rem;
        font-weight: 800;
        margin: 0;
    }

    .printer-header p {
        color: rgba(255, 255, 255, 0.78);
        margin: .35rem 0 0;
    }

    .printer-stat {
        background: #fff;
        border: 1px solid #e5edf7;
        border-radius: 14px;
        box-shadow: 0 15px 35px rgba(15, 23, 42, 0.07);
        padding: 1rem;
        min-height: 96px;
    }

    .printer-stat .icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .printer-list-card {
        background: #fff;
        border: 1px solid #e5edf7;
        border-radius: 18px;
        box-shadow: 0 18px 42px rgba(15, 23, 42, 0.07);
        overflow: hidden;
    }

    .printer-row {
        display: grid;
        grid-template-columns: 56px minmax(220px, 1.1fr) minmax(160px, .7fr) minmax(150px, .7fr) auto;
        gap: 1rem;
        align-items: center;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid #edf2f7;
    }

    .printer-row:last-child {
        border-bottom: 0;
    }

    .printer-avatar {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #6d28d9, #f97316);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .25);
    }

    .printer-name {
        font-size: .98rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: .25rem;
    }

    .printer-meta {
        color: #64748b;
        font-size: .82rem;
    }

    .printer-badges {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        margin-top: .45rem;
    }

    .soft-badge {
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 800;
        padding: .3rem .55rem;
    }

    .soft-badge.success {
        background: #dcfce7;
        color: #15803d;
    }

    .soft-badge.muted {
        background: #f1f5f9;
        color: #475569;
    }

    .soft-badge.warning {
        background: #fef3c7;
        color: #a16207;
    }

    .soft-badge.info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .printer-actions {
        display: flex;
        justify-content: flex-end;
        gap: .45rem;
        flex-wrap: wrap;
    }

    .printer-empty {
        text-align: center;
        padding: 4rem 1rem;
    }

    .printer-empty .empty-icon {
        width: 74px;
        height: 74px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #6d28d9;
        background: #f3e8ff;
        font-size: 1.7rem;
        margin-bottom: 1rem;
    }

    .discovery-list {
        display: grid;
        gap: .75rem;
    }

    .discovery-item {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: .8rem;
    }

    @media (max-width: 992px) {
        .printer-row {
            grid-template-columns: 48px 1fr;
        }

        .printer-row > .printer-col-wide,
        .printer-row > .printer-actions {
            grid-column: 1 / -1;
        }

        .printer-actions {
            justify-content: flex-start;
        }
    }
</style>
@endsection

@section('content')
<div class="printer-shell">
    <div class="printer-header mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1>Printer Settings</h1>
                <p>Manage KOT, invoice, USB, Bluetooth, and network printer configuration.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-light" id="discoverPrintersHeaderBtn">
                    <i class="fas fa-search me-2"></i>Discover Printers
                </button>
                <a href="{{ route('restaurant.printers.create') }}" class="btn btn-warning fw-bold">
                    <i class="fas fa-plus me-2"></i>Add Printer
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="printer-stat d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Total Printers</div>
                    <div class="h3 fw-bold mb-0">{{ $printerStats['total'] }}</div>
                </div>
                <span class="icon bg-primary-subtle text-primary"><i class="fas fa-print"></i></span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="printer-stat d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Active Printers</div>
                    <div class="h3 fw-bold mb-0">{{ $printerStats['active'] }}</div>
                </div>
                <span class="icon bg-success-subtle text-success"><i class="fas fa-check"></i></span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="printer-stat d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Default Printer</div>
                    <div class="h3 fw-bold mb-0">{{ $printerStats['default'] }}</div>
                </div>
                <span class="icon bg-warning-subtle text-warning"><i class="fas fa-star"></i></span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="printer-stat d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Network Printers</div>
                    <div class="h3 fw-bold mb-0">{{ $printerStats['network'] }}</div>
                </div>
                <span class="icon bg-info-subtle text-info"><i class="fas fa-wifi"></i></span>
            </div>
        </div>
    </div>

    <div class="printer-list-card">
        @forelse($printerRows as $printer)
            <div class="printer-row">
                <div class="printer-avatar">
                    @if($printer['type'] === 'network')
                        <i class="fas fa-network-wired"></i>
                    @elseif($printer['type'] === 'bluetooth')
                        <i class="fas fa-bluetooth"></i>
                    @else
                        <i class="fas fa-usb"></i>
                    @endif
                </div>

                <div>
                    <div class="printer-name">{{ $printer['name'] }}</div>
                    <div class="printer-meta">
                        {{ $printer['type_label'] }} printer · {{ $printer['paper_size'] }}mm paper · Added {{ $printer['created_at'] }}
                    </div>
                    <div class="printer-badges">
                        @if($printer['is_active'])
                            <span class="soft-badge success">Active</span>
                        @else
                            <span class="soft-badge muted">Inactive</span>
                        @endif
                        @if($printer['is_default'])
                            <span class="soft-badge warning">Default</span>
                        @endif
                        <span class="soft-badge info">{{ $printer['type_label'] }}</span>
                    </div>
                </div>

                <div class="printer-col-wide">
                    <div class="text-muted small fw-semibold">Connection</div>
                    <div class="fw-bold text-dark">{{ $printer['connection'] }}</div>
                </div>

                <div>
                    <div class="text-muted small fw-semibold">Paper Size</div>
                    <div class="fw-bold text-dark">{{ $printer['paper_size'] }}mm</div>
                </div>

                <div class="printer-actions">
                    <button type="button"
                            class="btn btn-sm btn-outline-info test-printer"
                            data-test-url="{{ route('restaurant.printers.test', $printer['id']) }}"
                            data-name="{{ $printer['name'] }}">
                        <i class="fas fa-vial me-1"></i>Test
                    </button>

                    @if(!$printer['is_default'])
                        <form action="{{ route('restaurant.printers.set-default', $printer['id']) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-star me-1"></i>Default
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('restaurant.printers.edit', $printer['id']) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>

                    <button type="button"
                            class="btn btn-sm btn-outline-danger delete-printer"
                            data-destroy-url="{{ route('restaurant.printers.destroy', $printer['id']) }}"
                            data-name="{{ $printer['name'] }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        @empty
            <div class="printer-empty">
                <div class="empty-icon"><i class="fas fa-print"></i></div>
                <h4 class="fw-bold mb-2">No Printers Configured</h4>
                <p class="text-muted mb-3">Add a printer to print KOT and invoices from restaurant orders.</p>
                <div class="d-flex justify-content-center flex-wrap gap-2">
                    <a href="{{ route('restaurant.printers.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Manually
                    </a>
                    <button type="button" class="btn btn-outline-primary" id="discoverPrintersEmptyBtn">
                        <i class="fas fa-search me-2"></i>Auto-Discover
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    <div class="row g-3 mt-3">
        <div class="col-md-4">
            <div class="printer-stat">
                <div class="fw-bold mb-1"><i class="fas fa-wifi text-primary me-2"></i>Network Printer</div>
                <div class="text-muted small">Use the printer IP and port for WiFi or ethernet thermal printers.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="printer-stat">
                <div class="fw-bold mb-1"><i class="fas fa-bluetooth text-info me-2"></i>Bluetooth Printer</div>
                <div class="text-muted small">Pair the device first, then save its Bluetooth address.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="printer-stat">
                <div class="fw-bold mb-1"><i class="fas fa-usb text-success me-2"></i>USB Printer</div>
                <div class="text-muted small">Use the local USB path configured on the restaurant terminal.</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="testPrintModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-print me-2 text-primary"></i>Test Printer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="testPrintResult" class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                    <p>Preparing test print...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deletePrinterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Printer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Delete <strong id="deletePrinterName"></strong>?</p>
                <p class="text-muted small mb-0">This printer will no longer be available for KOT or invoice printing.</p>
            </div>
            <div class="modal-footer border-0">
                <form id="deletePrinterForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Printer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="discoveryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-search me-2 text-primary"></i>Discover Printers
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="btn-group w-100 mb-3" role="group">
                    <button type="button" class="btn btn-outline-primary discovery-tab active" data-type="all">All</button>
                    <button type="button" class="btn btn-outline-primary discovery-tab" data-type="network">Network</button>
                    <button type="button" class="btn btn-outline-primary discovery-tab" data-type="bluetooth">Bluetooth</button>
                    <button type="button" class="btn btn-outline-primary discovery-tab" data-type="usb">USB</button>
                </div>
                <div id="discoveryResults" class="bg-light rounded-3 p-3" style="min-height: 260px;"
                     data-discover-url="{{ route('restaurant.printers.discover') }}"
                     data-pair-url="{{ route('restaurant.printers.pair-bluetooth') }}"
                     data-create-url="{{ route('restaurant.printers.create') }}">
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3 d-block"></i>
                        <p class="mb-3">Scan for available printers on this restaurant terminal.</p>
                        <button type="button" class="btn btn-primary" id="startDiscoveryBtn">
                            <i class="fas fa-play me-2"></i>Start Discovery
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;"></div>
@endsection

@section('scripts')
<script>
    (function () {
        var currentDiscoveryType = 'all';
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        function byId(id) {
            return document.getElementById(id);
        }

        function escapeHtml(value) {
            var div = document.createElement('div');
            div.textContent = value || '';
            return div.innerHTML;
        }

        function showToast(message, type) {
            var container = byId('toastContainer');
            if (!container) {
                return;
            }

            var toast = document.createElement('div');
            var background = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-primary');
            toast.className = 'toast align-items-center text-white ' + background + ' border-0 mb-2';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + escapeHtml(message) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            container.appendChild(toast);

            if (window.bootstrap && bootstrap.Toast) {
                var bsToast = new bootstrap.Toast(toast, { delay: 3000 });
                bsToast.show();
                toast.addEventListener('hidden.bs.toast', function () {
                    toast.remove();
                });
            }
        }

        function openDiscoveryModal() {
            var modalElement = byId('discoveryModal');
            if (modalElement && window.bootstrap) {
                new bootstrap.Modal(modalElement).show();
            }
        }

        function setLoadingDiscovery() {
            var results = byId('discoveryResults');
            if (results) {
                results.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i><p>Scanning for ' + escapeHtml(currentDiscoveryType) + ' printers...</p><small class="text-muted">This may take a few seconds</small></div>';
            }
        }

        function renderPrinterGroup(htmlParts, label, icon, type, items) {
            if (!items || !items.length) {
                return;
            }

            htmlParts.push('<div class="fw-bold text-muted small mt-2 mb-2"><i class="' + icon + ' me-2"></i>' + label + '</div>');

            for (var index = 0; index < items.length; index++) {
                var printer = items[index];
                var name = printer.name || label + ' Printer';
                var detail = '';
                var extraData = '';

                if (type === 'network') {
                    detail = 'IP: ' + (printer.ip || 'N/A') + ':' + (printer.port || '9100');
                    extraData = ' data-ip="' + escapeHtml(printer.ip || '') + '" data-port="' + escapeHtml(printer.port || '') + '"';
                } else if (type === 'bluetooth') {
                    detail = 'MAC: ' + (printer.mac || 'N/A');
                    extraData = ' data-mac="' + escapeHtml(printer.mac || '') + '"';
                } else {
                    detail = 'Device: ' + (printer.device_path || printer.port || 'USB001');
                    extraData = ' data-path="' + escapeHtml(printer.device_path || printer.port || 'USB001') + '"';
                }

                htmlParts.push('<div class="discovery-item"><div class="d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><div class="fw-bold"><i class="' + icon + ' me-2"></i>' + escapeHtml(name) + '</div><div class="text-muted small">' + escapeHtml(detail) + '</div></div><button type="button" class="btn btn-sm btn-primary use-discovered-printer" data-type="' + type + '" data-name="' + escapeHtml(name) + '"' + extraData + '><i class="fas fa-plus me-1"></i>Use</button></div></div>');
            }
        }

        function wireDiscoveredPrinterButtons() {
            var results = byId('discoveryResults');
            if (!results) {
                return;
            }

            var createUrl = results.getAttribute('data-create-url');
            var buttons = document.querySelectorAll('.use-discovered-printer');
            for (var index = 0; index < buttons.length; index++) {
                buttons[index].addEventListener('click', function () {
                    localStorage.setItem('selected_printer', JSON.stringify({
                        type: this.getAttribute('data-type'),
                        name: this.getAttribute('data-name'),
                        ip: this.getAttribute('data-ip'),
                        port: this.getAttribute('data-port'),
                        mac: this.getAttribute('data-mac'),
                        path: this.getAttribute('data-path')
                    }));
                    window.location.href = createUrl + '?from_discovery=1';
                });
            }
        }

        function renderDiscovery(printers) {
            var results = byId('discoveryResults');
            var htmlParts = ['<div class="discovery-list">'];

            renderPrinterGroup(htmlParts, 'Network Printers', 'fas fa-wifi text-primary', 'network', printers.network);
            renderPrinterGroup(htmlParts, 'Bluetooth Printers', 'fas fa-bluetooth text-info', 'bluetooth', printers.bluetooth);
            renderPrinterGroup(htmlParts, 'USB Printers', 'fas fa-usb text-success', 'usb', printers.usb);

            if (htmlParts.length === 1) {
                results.innerHTML = '<div class="text-center py-5"><i class="fas fa-search fa-3x text-muted mb-3"></i><p>No printers found</p><small class="text-muted">Make sure the printer is powered on and connected.</small></div>';
                return;
            }

            htmlParts.push('</div>');
            results.innerHTML = htmlParts.join('');
            wireDiscoveredPrinterButtons();
        }

        function startDiscovery() {
            var results = byId('discoveryResults');
            if (!results) {
                return;
            }

            setLoadingDiscovery();

            fetch(results.getAttribute('data-discover-url') + '?type=' + encodeURIComponent(currentDiscoveryType), {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        renderDiscovery(data.printers || {});
                    } else {
                        results.innerHTML = '<div class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle fa-3x mb-3"></i><p>Failed to discover printers</p></div>';
                    }
                })
                .catch(function () {
                    results.innerHTML = '<div class="text-center py-5 text-danger"><i class="fas fa-times-circle fa-3x mb-3"></i><p>Error scanning for printers</p></div>';
                });
        }

        function wireTests() {
            var buttons = document.querySelectorAll('.test-printer');
            for (var index = 0; index < buttons.length; index++) {
                buttons[index].addEventListener('click', function () {
                    var modalElement = byId('testPrintModal');
                    var result = byId('testPrintResult');
                    var printerName = this.getAttribute('data-name');
                    var testUrl = this.getAttribute('data-test-url');

                    if (result) {
                        result.innerHTML = '<i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><p>Sending test print to <strong>' + escapeHtml(printerName) + '</strong>...</p>';
                    }

                    if (modalElement && window.bootstrap) {
                        new bootstrap.Modal(modalElement).show();
                    }

                    fetch(testUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                        .then(function (response) {
                            return response.json();
                        })
                        .then(function (data) {
                            if (data.success) {
                                result.innerHTML = '<i class="fas fa-check-circle fa-3x text-success mb-3"></i><p class="text-success fw-bold">Test print sent successfully.</p><p class="small text-muted">Check the printer for the test page.</p>';
                                showToast('Test print sent successfully.', 'success');
                            } else {
                                result.innerHTML = '<i class="fas fa-times-circle fa-3x text-danger mb-3"></i><p class="text-danger fw-bold">Test print failed.</p><p class="small">' + escapeHtml(data.message || 'Failed to connect to printer') + '</p>';
                                showToast(data.message || 'Failed to connect to printer.', 'error');
                            }
                        })
                        .catch(function () {
                            result.innerHTML = '<i class="fas fa-times-circle fa-3x text-danger mb-3"></i><p class="text-danger fw-bold">Connection error.</p><p class="small">Could not connect to printer.</p>';
                            showToast('Failed to connect to printer.', 'error');
                        });
                });
            }
        }

        function wireDeleteModal() {
            var buttons = document.querySelectorAll('.delete-printer');
            for (var index = 0; index < buttons.length; index++) {
                buttons[index].addEventListener('click', function () {
                    var form = byId('deletePrinterForm');
                    var name = byId('deletePrinterName');
                    var modalElement = byId('deletePrinterModal');

                    if (form) {
                        form.action = this.getAttribute('data-destroy-url');
                    }
                    if (name) {
                        name.textContent = this.getAttribute('data-name') || 'this printer';
                    }
                    if (modalElement && window.bootstrap) {
                        new bootstrap.Modal(modalElement).show();
                    }
                });
            }
        }

        function wireDiscovery() {
            var headerButton = byId('discoverPrintersHeaderBtn');
            var emptyButton = byId('discoverPrintersEmptyBtn');
            var startButton = byId('startDiscoveryBtn');
            var tabs = document.querySelectorAll('.discovery-tab');

            if (headerButton) {
                headerButton.addEventListener('click', openDiscoveryModal);
            }
            if (emptyButton) {
                emptyButton.addEventListener('click', openDiscoveryModal);
            }
            if (startButton) {
                startButton.addEventListener('click', startDiscovery);
            }

            for (var index = 0; index < tabs.length; index++) {
                tabs[index].addEventListener('click', function () {
                    for (var tabIndex = 0; tabIndex < tabs.length; tabIndex++) {
                        tabs[tabIndex].classList.remove('active');
                    }
                    this.classList.add('active');
                    currentDiscoveryType = this.getAttribute('data-type') || 'all';
                    startDiscovery();
                });
            }
        }

        wireTests();
        wireDeleteModal();
        wireDiscovery();
    })();
</script>
@endsection
