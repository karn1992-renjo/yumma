@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Add Printer')

@section('styles')
<style>
    .printer-type-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    .printer-type-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .printer-type-card.selected {
        border-color: var(--primary);
        background: rgba(255, 107, 53, 0.05);
    }
    .type-icon {
        width: 60px;
        height: 60px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
    }
    .discovered-printer-item {
        transition: all 0.2s ease;
    }
    .discovered-printer-item:hover {
        background: #F8FAFC;
        transform: translateX(5px);
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="mb-1">Add New Printer</h1>
                <p class="text-muted mb-0">Configure a thermal printer for KOT and invoice printing</p>
            </div>
            <a href="{{ route('restaurant.printers.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back to Printers
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Printer Configuration</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('restaurant.printers.store') }}" method="POST" id="printerForm">
                        @csrf

                        <!-- Printer Type Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold mb-3">Printer Type</label>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="printer-type-card card text-center p-3" data-type="network">
                                        <div class="type-icon bg-primary bg-opacity-10 mx-auto">
                                            <i class="fas fa-network-wired fa-2x text-primary"></i>
                                        </div>
                                        <h6 class="mb-1">Network Printer</h6>
                                        <small class="text-muted">WiFi / Ethernet</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="printer-type-card card text-center p-3" data-type="bluetooth">
                                        <div class="type-icon bg-info bg-opacity-10 mx-auto">
                                            <i class="fas fa-bluetooth fa-2x text-info"></i>
                                        </div>
                                        <h6 class="mb-1">Bluetooth Printer</h6>
                                        <small class="text-muted">Wireless</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="printer-type-card card text-center p-3" data-type="usb">
                                        <div class="type-icon bg-success bg-opacity-10 mx-auto">
                                            <i class="fas fa-usb fa-2x text-success"></i>
                                        </div>
                                        <h6 class="mb-1">USB Printer</h6>
                                        <small class="text-muted">Direct Connection</small>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="printer_type" id="printerType" value="network" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Printer Name <span class="text-danger">*</span></label>
                            <input type="text" name="printer_name" class="form-control" required placeholder="e.g., Kitchen Printer, Front Counter, Bar Printer">
                            <small class="text-muted">Give your printer a recognizable name</small>
                        </div>

                        <!-- Network Printer Fields -->
                        <div id="networkFields" class="printer-fields">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">IP Address <span class="text-danger">*</span></label>
                                <input type="text" name="ip_address" class="form-control" placeholder="192.168.1.100">
                                <small class="text-muted">Example: 192.168.1.100</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Port</label>
                                <input type="number" name="port" class="form-control" value="9100" placeholder="9100">
                                <small class="text-muted">Default thermal printer port is 9100</small>
                            </div>
                        </div>

                        <!-- Bluetooth Printer Fields -->
                        <div id="bluetoothFields" class="printer-fields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Bluetooth MAC Address <span class="text-danger">*</span></label>
                                <input type="text" name="bluetooth_mac" class="form-control" placeholder="XX:XX:XX:XX:XX:XX">
                                <small class="text-muted">Example: 00:1A:7D:DA:71:13</small>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Make sure Bluetooth is enabled and printer is paired with your system.
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="scanBluetoothBtn">
                                    <i class="fas fa-search me-1"></i> Scan for Bluetooth Printers
                                </button>
                            </div>
                        </div>

                        <!-- USB Printer Fields -->
                        <div id="usbFields" class="printer-fields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">USB Device Path</label>
                                <input type="text" name="usb_path" class="form-control" placeholder="/dev/usb/lp0">
                                <small class="text-muted">
                                    Linux: /dev/usb/lp0 | Windows: USB001 | macOS: /dev/cu.usbmodem*
                                </small>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                USB printers are usually auto-detected. Leave empty for auto-detection.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Paper Size (mm)</label>
                            <select name="paper_size" class="form-select">
                                <option value="58">58mm (Standard Thermal - Most Common)</option>
                                <option value="80">80mm (Large Thermal)</option>
                            </select>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_default" class="form-check-input" id="isDefault">
                            <label class="form-check-label" for="isDefault">
                                Set as default printer
                            </label>
                            <br>
                            <small class="text-muted">Default printer will be used for automatic KOT and invoice prints</small>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive" checked>
                            <label class="form-check-label" for="isActive">
                                Active
                            </label>
                            <br>
                            <small class="text-muted">Inactive printers won't receive print jobs</small>
                        </div>

                        <hr>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Before saving:</strong> Make sure your printer is powered ON and properly connected.
                            You can test the connection after saving.
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i> Save Printer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <!-- Auto Discovery Panel -->
            <div class="table-card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-search me-2 text-primary"></i> Auto Discovery
                        </h5>
                        <button type="button" class="btn btn-sm btn-primary" id="discoverBtn">
                            <i class="fas fa-play me-1"></i> Scan Now
                        </button>
                    </div>
                </div>
                <div class="p-3" id="discoveryPanel" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-wifi fa-2x mb-2 d-block"></i>
                        <small>Click "Scan Now" to find printers on your network</small>
                    </div>
                </div>
            </div>

            <!-- Test Print Panel -->
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-microphone me-2 text-info"></i> Test Print Sample
                    </h5>
                </div>
                <div class="p-4">
                    <div class="bg-light rounded p-3 mb-3">
                        <pre class="small mb-0" style="font-family: monospace; white-space: pre-wrap;">
================================
    YOUR RESTAURANT NAME
        123 Main Street
        City, State 12345
        Phone: +91 XXXXX XXXXX
================================

      KITCHEN ORDER TICKET
--------------------------------
Order #: ORD-12345
Date: {{ date('Y-m-d H:i:s') }}
Customer: John Doe
--------------------------------
ITEMS:
--------------------------------
Butter Chicken            1 x {{ $currencySymbol }}350
Garlic Naan               2 x {{ $currencySymbol }}60
Veg Biryani               1 x {{ $currencySymbol }}250
--------------------------------
Subtotal:               {{ $currencySymbol }}720
Tax (5%):               {{ $currencySymbol }}36
Delivery Fee:            {{ $currencySymbol }}40
--------------------------------
TOTAL:                  {{ $currencySymbol }}796
--------------------------------
Thank you for ordering!
================================</pre>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Sample KOT Format:</strong> This is how your kitchen order tickets will look.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scanner Modal -->
<div class="modal fade" id="scannerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-bluetooth me-2 text-info"></i> Bluetooth Scanner
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                <div id="scanResults">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                        <p>Scanning for Bluetooth printers...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Printer Type Selection
    const printerTypeCards = document.querySelectorAll('.printer-type-card');
    const printerTypeInput = document.getElementById('printerType');
    const networkFields = document.getElementById('networkFields');
    const bluetoothFields = document.getElementById('bluetoothFields');
    const usbFields = document.getElementById('usbFields');
    
    function selectPrinterType(type) {
        printerTypeInput.value = type;
        
        // Update UI
        printerTypeCards.forEach(card => {
            if (card.dataset.type === type) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
        
        // Show/hide fields
        networkFields.style.display = type === 'network' ? 'block' : 'none';
        bluetoothFields.style.display = type === 'bluetooth' ? 'block' : 'none';
        usbFields.style.display = type === 'usb' ? 'block' : 'none';
        
        // Update required attributes
        document.querySelector('[name="ip_address"]').required = type === 'network';
        document.querySelector('[name="bluetooth_mac"]').required = type === 'bluetooth';
    }
    
    printerTypeCards.forEach(card => {
        card.addEventListener('click', () => {
            selectPrinterType(card.dataset.type);
        });
    });
    
    // Auto Discovery
    let discoveryTimeout;
    
    document.getElementById('discoverBtn')?.addEventListener('click', async function() {
        const panel = document.getElementById('discoveryPanel');
        const btn = this;
        
        panel.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                <p>Scanning for printers...</p>
                <small class="text-muted">This may take 10-15 seconds</small>
            </div>
        `;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Scanning...';
        
        try {
            const response = await fetch('{{ route("restaurant.printers.discover") }}', {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play me-1"></i> Scan Now';
            
            if (data.success) {
                displayDiscoveryResults(data.printers);
            } else {
                panel.innerHTML = `
                    <div class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p>Failed to scan for printers</p>
                    </div>
                `;
            }
        } catch (error) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play me-1"></i> Scan Now';
            panel.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                    <p>Error scanning for printers</p>
                    <small>Please check your connection</small>
                </div>
            `;
        }
    });
    
    function displayDiscoveryResults(printers) {
        const panel = document.getElementById('discoveryPanel');
        let html = '';
        
        // Network Printers
        if (printers.network && printers.network.length > 0) {
            html += '<div class="mb-3"><div class="fw-bold text-primary mb-2"><i class="fas fa-wifi me-1"></i>Network Printers</div>';
            printers.network.forEach(printer => {
                html += `
                    <div class="discovered-printer-item p-2 rounded mb-2 cursor-pointer" 
                         onclick="useDiscoveredPrinter('network', '${escapeHtml(printer.name)}', '${printer.ip}', '${printer.port}')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-print text-primary me-2"></i>
                                <strong>${escapeHtml(printer.name)}</strong>
                                <br><small class="text-muted">${printer.ip}:${printer.port}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        // Bluetooth Printers
        if (printers.bluetooth && printers.bluetooth.length > 0) {
            html += '<div class="mb-3"><div class="fw-bold text-info mb-2"><i class="fas fa-bluetooth me-1"></i>Bluetooth Printers</div>';
            printers.bluetooth.forEach(printer => {
                html += `
                    <div class="discovered-printer-item p-2 rounded mb-2" 
                         onclick="useDiscoveredPrinter('bluetooth', '${escapeHtml(printer.name)}', '', '', '${printer.mac || ''}')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-bluetooth text-info me-2"></i>
                                <strong>${escapeHtml(printer.name)}</strong>
                                <br><small class="text-muted">MAC: ${printer.mac || 'N/A'} | Status: ${printer.status || 'Available'}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        // USB Printers
        if (printers.usb && printers.usb.length > 0) {
            html += '<div class="mb-3"><div class="fw-bold text-success mb-2"><i class="fas fa-usb me-1"></i>USB Printers</div>';
            printers.usb.forEach(printer => {
                html += `
                    <div class="discovered-printer-item p-2 rounded mb-2" 
                         onclick="useDiscoveredPrinter('usb', '${escapeHtml(printer.name)}', '', '', '', '${printer.device_path || ''}')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-usb text-success me-2"></i>
                                <strong>${escapeHtml(printer.name)}</strong>
                                <br><small class="text-muted">Device: ${printer.device_path || printer.port || 'USB001'}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        if (!html) {
            html = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-search fa-2x mb-2 d-block"></i>
                    <p>No printers found</p>
                    <small>Make sure your printer is powered on and connected</small>
                </div>
            `;
        }
        
        panel.innerHTML = html;
    }
    
    function useDiscoveredPrinter(type, name, ip, port, mac, path) {
        // Select printer type
        selectPrinterType(type);
        
        // Fill form fields
        document.querySelector('[name="printer_name"]').value = name || '';
        
        if (type === 'network') {
            document.querySelector('[name="ip_address"]').value = ip || '';
            document.querySelector('[name="port"]').value = port || '9100';
        } else if (type === 'bluetooth') {
            document.querySelector('[name="bluetooth_mac"]').value = mac || '';
        } else if (type === 'usb') {
            document.querySelector('[name="usb_path"]').value = path || '';
        }
        
        // Scroll to form
        document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
        
        // Show success message
        showToast(`Selected printer: ${name}`, 'success');
    }
    
    // Bluetooth Scanner
    document.getElementById('scanBluetoothBtn')?.addEventListener('click', async function() {
        const modal = new bootstrap.Modal(document.getElementById('scannerModal'));
        const resultsDiv = document.getElementById('scanResults');
        
        modal.show();
        resultsDiv.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                <p>Scanning for Bluetooth devices...</p>
                <small class="text-muted">This may take 10 seconds</small>
            </div>
        `;
        
        try {
            const response = await fetch('{{ route("restaurant.printers.discover") }}?type=bluetooth', {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            
            if (data.success && data.printers.bluetooth) {
                let html = '<div class="list-group">';
                data.printers.bluetooth.forEach(printer => {
                    html += `
                        <div class="list-group-item list-group-item-action" 
                             onclick="selectBluetoothPrinter('${escapeHtml(printer.name)}', '${printer.mac || ''}')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-bluetooth text-info me-2"></i>
                                    <strong>${escapeHtml(printer.name)}</strong>
                                    <br><small class="text-muted">MAC: ${printer.mac || 'N/A'}</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                resultsDiv.innerHTML = html;
            } else {
                resultsDiv.innerHTML = `
                    <div class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p>No Bluetooth printers found</p>
                    </div>
                `;
            }
        } catch (error) {
            resultsDiv.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                    <p>Failed to scan for devices</p>
                </div>
            `;
        }
    });
    
    function selectBluetoothPrinter(name, mac) {
        document.querySelector('[name="printer_name"]').value = name;
        document.querySelector('[name="bluetooth_mac"]').value = mac;
        selectPrinterType('bluetooth');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('scannerModal'));
        modal.hide();
        
        showToast(`Selected: ${name}`, 'success');
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showToast(message, type = 'info') {
        const toastContainer = document.createElement('div');
        toastContainer.className = `position-fixed bottom-0 end-0 p-3`;
        toastContainer.style.zIndex = '1100';
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        document.body.appendChild(toastContainer);
        
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toastContainer.remove();
        });
    }
    
    // Check if coming from discovery modal
    if (window.location.search.includes('from_discovery=1')) {
        const selectedPrinter = localStorage.getItem('selected_printer');
        if (selectedPrinter) {
            const printer = JSON.parse(selectedPrinter);
            useDiscoveredPrinter(printer.type, printer.name, printer.ip, printer.port, printer.mac, printer.path);
            localStorage.removeItem('selected_printer');
        } else {
            selectPrinterType('network');
        }
    } else {
        // Initialize default selection only when there is no discovered printer stored
        selectPrinterType('network');
    }
</script>
@endsection
