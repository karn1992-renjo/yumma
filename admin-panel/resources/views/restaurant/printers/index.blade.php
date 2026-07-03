@extends('layouts.restaurant')

@section('title', 'Printer Settings')

@section('styles')
<style>
    .printer-card {
        transition: all 0.3s ease;
        border-radius: 16px;
        overflow: hidden;
    }
    .printer-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .printer-status-badge {
        position: absolute;
        top: 15px;
        right: 15px;
    }
    .test-result {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    }
    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.7; }
        100% { transform: scale(1); opacity: 1; }
    }
    .printer-testing {
        animation: pulse 1s infinite;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="mb-1">Printer Settings</h1>
                <p class="text-muted mb-0">Manage your thermal printers for KOT and invoice printing</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" id="discoverPrintersHeaderBtn">
                    <i class="fas fa-search me-2"></i> Discover Printers
                </button>
                <a href="{{ route('restaurant.printers.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Add Printer
                </a>
            </div>
        </div>
    </div>

    <!-- Printer Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Total Printers</h6>
                        <h3 class="mb-0">{{ $printers->count() }}</h3>
                    </div>
                    <div class="icon primary">
                        <i class="fas fa-print"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Active Printers</h6>
                        <h3 class="mb-0">{{ $printers->where('is_active', true)->count() }}</h3>
                    </div>
                    <div class="icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Default Printer</h6>
                        <h3 class="mb-0">{{ $printers->where('is_default', true)->count() }}</h3>
                    </div>
                    <div class="icon warning">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Network Printers</h6>
                        <h3 class="mb-0">{{ $printers->where('printer_type', 'network')->count() }}</h3>
                    </div>
                    <div class="icon info">
                        <i class="fas fa-wifi"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Printers Grid -->
    <div class="row">
        @forelse($printers as $printer)
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="stat-card printer-card position-relative">
                <div class="printer-status-badge">
                    @if($printer->is_active)
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                    @if($printer->is_default)
                        <span class="badge bg-warning ms-1">Default</span>
                    @endif
                </div>
                
                <div class="text-center mb-3">
                    <div class="printer-icon mb-2">
                        @if($printer->printer_type == 'network')
                            <i class="fas fa-network-wired fa-3x text-primary"></i>
                        @elseif($printer->printer_type == 'bluetooth')
                            <i class="fas fa-bluetooth fa-3x text-info"></i>
                        @else
                            <i class="fas fa-usb fa-3x text-success"></i>
                        @endif
                    </div>
                    <h5 class="mb-1 fw-bold">{{ $printer->printer_name }}</h5>
                    <p class="text-muted small mb-0">
                        {{ ucfirst($printer->printer_type) }} Printer | {{ $printer->paper_size }}mm
                    </p>
                </div>

                <div class="mb-3">
                    <div class="bg-light rounded p-3">
                        @if($printer->printer_type == 'network')
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">IP Address:</span>
                                <span class="fw-semibold">{{ $printer->ip_address }}:{{ $printer->port }}</span>
                            </div>
                        @elseif($printer->printer_type == 'bluetooth')
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Bluetooth MAC:</span>
                                <span class="fw-semibold">{{ $printer->bluetooth_mac ?? 'Not set' }}</span>
                            </div>
                        @elseif($printer->printer_type == 'usb')
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">USB Path:</span>
                                <span class="fw-semibold">{{ $printer->usb_path ?? '/dev/usb/lp0' }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Added:</span>
                            <span class="small">{{ $printer->created_at->format('d M Y') }}</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-info flex-fill test-printer" 
                            data-id="{{ $printer->id }}"
                            data-name="{{ $printer->printer_name }}">
                        <i class="fas fa-microphone me-1"></i> Test
                    </button>
                    <button class="btn btn-sm btn-outline-primary flex-fill edit-printer" 
                            data-id="{{ $printer->id }}">
                        <i class="fas fa-edit me-1"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-printer" 
                            data-id="{{ $printer->id }}"
                            data-name="{{ $printer->printer_name }}">
                        <i class="fas fa-trash me-1"></i>
                    </button>
                </div>

                @if($printer->is_default)
                <div class="mt-3 text-center">
                    <small class="text-primary">
                        <i class="fas fa-star me-1"></i> Default Printer for KOT & Invoice
                    </small>
                </div>
                @elseif(!$printer->is_default && $printers->where('is_default', true)->count() == 0)
                <div class="mt-3 text-center">
                    <button class="btn btn-sm btn-link set-default-printer" data-id="{{ $printer->id }}">
                        <i class="fas fa-star me-1"></i> Set as Default
                    </button>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="table-card text-center py-5">
                <i class="fas fa-print fa-3x text-muted mb-3 d-block"></i>
                <h5 class="mb-2">No Printers Configured</h5>
                <p class="text-muted mb-3">Add a printer to print KOT (Kitchen Order Tickets) and invoices</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="{{ route('restaurant.printers.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add Manually
                    </a>
                    <button type="button" class="btn btn-outline-primary" id="discoverPrintersEmptyBtn">
                        <i class="fas fa-search me-2"></i> Auto-Discover Printers
                    </button>
                </div>
            </div>
        </div>
        @endforelse
    </div>

    <!-- Quick Tips -->
    <div class="table-card mt-4">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">Printer Setup Guide</h5>
        </div>
        <div class="p-4">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-wifi fa-2x text-primary"></i>
                        <div>
                            <h6 class="mb-0">Network Printer (WiFi/Ethernet)</h6>
                            <small class="text-muted">Connect via IP address (e.g., 192.168.1.100:9100)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-bluetooth fa-2x text-info"></i>
                        <div>
                            <h6 class="mb-0">Bluetooth Printer</h6>
                            <small class="text-muted">Pair via Bluetooth and enter MAC address</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-usb fa-2x text-success"></i>
                        <div>
                            <h6 class="mb-0">USB Printer</h6>
                            <small class="text-muted">Connected directly via USB port</small>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Tip:</strong> Use the "Discover Printers" button to automatically find printers on your network
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> Make sure your printer is powered on and connected before testing
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Print Modal -->
<div class="modal fade" id="testPrintModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-print me-2 text-primary"></i> Test Printer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="testPrintResult" class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                    <p>Sending test print to <strong id="printerName"></strong>...</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Printer Modal -->
<div class="modal fade" id="deletePrinterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> Delete Printer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete printer <strong id="deletePrinterName"></strong>?</p>
                <p class="text-muted small mb-0">This action cannot be undone.</p>
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

<!-- Discovery Modal -->
<div class="modal fade" id="discoveryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-search me-2 text-primary"></i> Discover Printers
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                <div class="discovery-tabs mb-3">
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-primary discovery-tab active" data-type="all">
                            <i class="fas fa-globe me-1"></i> All
                        </button>
                        <button type="button" class="btn btn-outline-primary discovery-tab" data-type="network">
                            <i class="fas fa-wifi me-1"></i> Network
                        </button>
                        <button type="button" class="btn btn-outline-primary discovery-tab" data-type="bluetooth">
                            <i class="fas fa-bluetooth me-1"></i> Bluetooth
                        </button>
                        <button type="button" class="btn btn-outline-primary discovery-tab" data-type="usb">
                            <i class="fas fa-usb me-1"></i> USB
                        </button>
                    </div>
                </div>
                <div id="discoveryResults" class="p-3 bg-light rounded-3" style="min-height: 300px;">
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3 d-block"></i>
                        <p>Click "Start Discovery" to scan for printers</p>
                        <button class="btn btn-primary" id="startDiscoveryBtn">
                            <i class="fas fa-play me-2"></i> Start Discovery
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

<!-- Edit Printer Modal -->
<div class="modal fade" id="editPrinterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form id="editPrinterForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-edit me-2 text-primary"></i> Edit Printer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="printer_id" id="editPrinterId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Printer Name</label>
                        <input type="text" name="printer_name" id="editPrinterName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Paper Size</label>
                        <select name="paper_size" id="editPaperSize" class="form-select">
                            <option value="58">58mm (Standard Thermal)</option>
                            <option value="80">80mm (Large Thermal)</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive" value="1">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_default" class="form-check-input" id="editIsDefault" value="1">
                        <label class="form-check-label" for="editIsDefault">Set as Default Printer</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;"></div>

@endsection

@section('scripts')
<script>
    let currentDiscoveryType = 'all';
    
    // Test Printer
    document.querySelectorAll('.test-printer').forEach(btn => {
        btn.addEventListener('click', async function() {
            const printerId = this.dataset.id;
            const printerName = this.dataset.name;
            const modal = new bootstrap.Modal(document.getElementById('testPrintModal'));
            const resultDiv = document.getElementById('testPrintResult');
            const printerNameSpan = document.getElementById('printerName');
            
            printerNameSpan.textContent = printerName;
            modal.show();
            
            resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><p>Sending test print...</p>';
            
            try {
                const response = await fetch(`/restaurant/printers/${printerId}/test`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="text-success fw-bold">Test print successful!</p>
                        <p class="small text-muted">Check your printer for the test page.</p>
                    `;
                    showToast('Test print sent successfully!', 'success');
                } else {
                    resultDiv.innerHTML = `
                        <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                        <p class="text-danger fw-bold">Test failed!</p>
                        <p class="small">${data.message || 'Failed to connect to printer'}</p>
                    `;
                    showToast(data.message || 'Failed to connect to printer', 'error');
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                    <p class="text-danger fw-bold">Connection Error!</p>
                    <p class="small">Could not connect to printer. Please check connection.</p>
                `;
                showToast('Failed to connect to printer', 'error');
            }
            
            setTimeout(() => {
                modal.hide();
            }, 3000);
        });
    });
    
    // Delete Printer
    document.querySelectorAll('.delete-printer').forEach(btn => {
        btn.addEventListener('click', function() {
            const printerId = this.dataset.id;
            const printerName = this.dataset.name;
            const modal = new bootstrap.Modal(document.getElementById('deletePrinterModal'));
            const form = document.getElementById('deletePrinterForm');
            const nameSpan = document.getElementById('deletePrinterName');
            
            nameSpan.textContent = printerName;
            form.action = `/restaurant/printers/${printerId}`;
            modal.show();
        });
    });
    
    // Edit Printer
    document.querySelectorAll('.edit-printer').forEach(btn => {
        btn.addEventListener('click', async function() {
            const printerId = this.dataset.id;
            
            try {
                const response = await fetch(`/restaurant/printers/${printerId}/edit`);
                const html = await response.text();
                
                // Parse printer data from the response or fetch via API
                const modal = new bootstrap.Modal(document.getElementById('editPrinterModal'));
                const form = document.getElementById('editPrinterForm');
                form.action = `/restaurant/printers/${printerId}`;
                
                // You would populate the form with printer data here
                modal.show();
            } catch (error) {
                showToast('Failed to load printer details', 'error');
            }
        });
    });
    
    // Set Default Printer
    document.querySelectorAll('.set-default-printer').forEach(btn => {
        btn.addEventListener('click', async function() {
            const printerId = this.dataset.id;
            
            try {
                const response = await fetch(`/restaurant/printers/${printerId}/set-default`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('Default printer updated!', 'success');
                    location.reload();
                } else {
                    showToast(data.message || 'Failed to set default printer', 'error');
                }
            } catch (error) {
                showToast('Failed to set default printer', 'error');
            }
        });
    });
    
    // Discovery Modal
    function openDiscoveryModal() {
        const modal = new bootstrap.Modal(document.getElementById('discoveryModal'));
        modal.show();
    }
    
    document.getElementById('discoverPrintersHeaderBtn')?.addEventListener('click', openDiscoveryModal);
    document.getElementById('discoverPrintersEmptyBtn')?.addEventListener('click', openDiscoveryModal);
    
    // Discovery Tabs
    document.querySelectorAll('.discovery-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.discovery-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentDiscoveryType = this.dataset.type;
            startDiscovery();
        });
    });
    
    // Start Discovery
    document.getElementById('startDiscoveryBtn')?.addEventListener('click', startDiscovery);
    
    async function startDiscovery() {
        const resultsDiv = document.getElementById('discoveryResults');
        resultsDiv.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                <p>Scanning for ${currentDiscoveryType} printers...</p>
                <small class="text-muted">This may take a few seconds</small>
            </div>
        `;
        
        try {
            const response = await fetch(`/restaurant/printers/discover?type=${currentDiscoveryType}`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            
            if (data.success) {
                displayDiscoveredPrinters(data.printers);
            } else {
                resultsDiv.innerHTML = `
                    <div class="text-center py-5 text-danger">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <p>Failed to discover printers</p>
                    </div>
                `;
            }
        } catch (error) {
            resultsDiv.innerHTML = `
                <div class="text-center py-5 text-danger">
                    <i class="fas fa-times-circle fa-3x mb-3"></i>
                    <p>Error scanning for printers</p>
                    <small>Please check your connection</small>
                </div>
            `;
        }
    }
    
    function displayDiscoveredPrinters(printers) {
        const resultsDiv = document.getElementById('discoveryResults');
        let html = '<div class="list-group">';
        
        // Network Printers
        if (printers.network && printers.network.length > 0) {
            html += '<div class="list-group-item bg-light fw-bold"><i class="fas fa-wifi me-2"></i>Network Printers (WiFi/Ethernet)</div>';
            printers.network.forEach(printer => {
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <i class="fas fa-print text-primary me-2"></i>
                                <strong>${escapeHtml(printer.name)}</strong>
                                <br><small class="text-muted">IP: ${printer.ip}:${printer.port}</small>
                                ${printer.model ? `<br><small class="text-muted">Model: ${printer.model}</small>` : ''}
                            </div>
                            <button class="btn btn-sm btn-primary use-discovered-printer" 
                                    data-type="network"
                                    data-name="${escapeHtml(printer.name)}"
                                    data-ip="${printer.ip || ''}"
                                    data-port="${printer.port || ''}">
                                <i class="fas fa-plus me-1"></i> Use This Printer
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        
        // Bluetooth Printers
        if (printers.bluetooth && printers.bluetooth.length > 0) {
            html += '<div class="list-group-item bg-light fw-bold"><i class="fas fa-bluetooth me-2"></i>Bluetooth Printers</div>';
            printers.bluetooth.forEach(printer => {
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <i class="fas fa-bluetooth text-info me-2"></i>
                                <strong>${escapeHtml(printer.name)}</strong>
                                <br><small class="text-muted">MAC: ${printer.mac || 'N/A'}</small>
                                <br><small class="text-muted">Status: ${printer.status || 'Available'}</small>
                            </div>
                            <div class="d-flex gap-2">
                                ${!printer.paired ? `<button class="btn btn-sm btn-outline-warning pair-bluetooth" data-mac="${printer.mac}"><i class="fas fa-handshake me-1"></i> Pair</button>` : ''}
                                <button class="btn btn-sm btn-primary use-discovered-printer" 
                                        data-type="bluetooth"
                                        data-name="${escapeHtml(printer.name)}"
                                        data-mac="${printer.mac || ''}">
                                    <i class="fas fa-plus me-1"></i> Use
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        // USB Printers
        if (printers.usb && printers.usb.length > 0) {
            html += '<div class="list-group-item bg-light fw-bold"><i class="fas fa-usb me-2"></i>USB Printers</div>';
            printers.usb.forEach(printer => {
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <i class="fas fa-usb text-success me-2"></i>
                                <strong>${escapeHtml(printer.name)}</strong>
                                <br><small class="text-muted">Device: ${printer.device_path || printer.port || 'USB001'}</small>
                            </div>
                            <button class="btn btn-sm btn-primary use-discovered-printer" 
                                    data-type="usb"
                                    data-name="${escapeHtml(printer.name)}"
                                    data-path="${printer.device_path || printer.port || 'USB001'}">
                                <i class="fas fa-plus me-1"></i> Use
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        
        if ((!printers.network || printers.network.length === 0) && 
            (!printers.bluetooth || printers.bluetooth.length === 0) && 
            (!printers.usb || printers.usb.length === 0)) {
            html = `
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <p>No printers found</p>
                    <small class="text-muted">Make sure your printer is powered on and connected</small>
                </div>
            `;
        } else {
            html += '</div>';
        }
        
        resultsDiv.innerHTML = html;
        
        // Add event listeners for "Use" buttons
        document.querySelectorAll('.use-discovered-printer').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.dataset.type;
                const name = this.dataset.name;
                
                // Store printer info and redirect to create page with pre-filled data
                localStorage.setItem('selected_printer', JSON.stringify({
                    type: type,
                    name: name,
                    ip: this.dataset.ip,
                    port: this.dataset.port,
                    mac: this.dataset.mac,
                    path: this.dataset.path
                }));
                
                window.location.href = '{{ route("restaurant.printers.create") }}?from_discovery=1';
            });
        });
        
        // Pair Bluetooth
        document.querySelectorAll('.pair-bluetooth').forEach(btn => {
            btn.addEventListener('click', async function() {
                const mac = this.dataset.mac;
                const btn = this;
                const originalText = btn.innerHTML;
                
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Pairing...';
                btn.disabled = true;
                
                try {
                    const response = await fetch('{{ route("restaurant.printers.pair-bluetooth") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ mac: mac })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        btn.innerHTML = '<i class="fas fa-check me-1"></i> Paired!';
                        btn.classList.remove('btn-outline-warning');
                        btn.classList.add('btn-success');
                        showToast('Printer paired successfully!', 'success');
                        setTimeout(() => startDiscovery(), 2000);
                    } else {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        showToast(data.message || 'Pairing failed', 'error');
                    }
                } catch (error) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    showToast('Failed to pair printer', 'error');
                }
            });
        });
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0 mb-2`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        container.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }
    
    // Check if coming from discovery
    if (window.location.search.includes('from_discovery=1')) {
        const selectedPrinter = localStorage.getItem('selected_printer');
        if (selectedPrinter) {
            const printer = JSON.parse(selectedPrinter);
            // Pre-fill form fields (will be handled in create page)
            localStorage.removeItem('selected_printer');
            showToast(`Selected: ${printer.name}`, 'success');
        }
    }
</script>
@endsection
