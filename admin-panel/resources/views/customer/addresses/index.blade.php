@extends('layouts.app')

@section('title', 'My Addresses')

@section('styles')
<style>
    .add-address-tile {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px;
        border-radius: 18px;
        border: 1.4px dashed color-mix(in srgb, var(--primary) 50%, transparent);
        background: transparent;
        cursor: pointer;
        margin-bottom: 16px;
        width: 100%;
        text-align: left;
    }

    .add-address-tile:hover {
        background: color-mix(in srgb, var(--primary) 4%, transparent);
    }

    .add-address-icon {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        background: color-mix(in srgb, var(--primary) 12%, white);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 16px;
    }

    .add-address-title {
        font-weight: 800;
        font-size: 15px;
        color: var(--app-ink, #111827);
    }

    .add-address-sub {
        font-size: 12.5px;
        color: var(--app-muted, #6B7280);
        font-weight: 500;
    }

    .add-address-tile .fa-chevron-right {
        margin-left: auto;
        color: #9CA3AF;
    }

    .address-card {
        background: white;
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 16px;
        border: 1px solid var(--app-border, #E5E7EB);
        transition: all 0.2s;
        position: relative;
    }

    .address-card:hover {
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08);
    }

    .address-card-top {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }

    .address-icon-tile {
        width: 44px;
        height: 44px;
        border-radius: 13px;
        background: #F3F4F6;
        color: #4B5563;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 16px;
    }

    .address-name-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        min-width: 0;
    }

    .address-name {
        font-weight: 800;
        font-size: 16px;
        color: var(--app-ink, #111827);
    }

    .default-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 3px 10px;
        font-size: 11px;
        font-weight: 800;
        background: color-mix(in srgb, var(--primary) 12%, white);
        color: var(--primary);
        border: 1px solid color-mix(in srgb, var(--primary) 24%, transparent);
    }

    .address-line {
        font-size: 12.5px;
        color: var(--app-muted, #6B7280);
        font-weight: 500;
        line-height: 1.5;
        margin-bottom: 4px;
    }

    .address-divider {
        border-top: 1px solid var(--app-border, #E5E7EB);
        margin: 12px 0;
    }

    .address-actions {
        display: flex;
        gap: 18px;
    }

    .address-action-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 700;
        color: var(--app-muted, #6B7280);
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
    }

    .address-action-link.danger {
        color: var(--app-danger, #E53935);
    }
</style>
@endsection

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">My Addresses</h2>
                    <p class="text-muted">Manage your delivery addresses</p>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            
            <!-- Add Address tile (dashed, matches the app's add-address entry point) -->
            <button type="button" class="add-address-tile" data-bs-toggle="modal" data-bs-target="#addressModal">
                <span class="add-address-icon"><i class="fas fa-plus"></i></span>
                <span>
                    <span class="add-address-title d-block">Add a new address</span>
                    <span class="add-address-sub">Home, work or a go-to spot</span>
                </span>
                <i class="fas fa-chevron-right"></i>
            </button>

            <!-- Addresses List -->
            @if($addresses->isEmpty())
                <div class="card shadow-sm text-center py-5" style="border-radius: var(--app-card-radius, 24px);">
                    <div class="card-body">
                        <i class="fas fa-map-marker-alt fa-4x text-muted mb-3"></i>
                        <h4>No Addresses Saved</h4>
                        <p class="text-muted">Add your first delivery address</p>
                    </div>
                </div>
            @else
                @foreach($addresses as $address)
                @php
                    $nameLower = strtolower($address->name ?? '');
                    $addrIcon = match(true) {
                        $nameLower === 'home' => 'fa-house',
                        $nameLower === 'work' => 'fa-briefcase',
                        str_contains($nameLower, 'hotel') || str_contains($nameLower, 'guest') => 'fa-bed',
                        default => 'fa-location-dot',
                    };
                @endphp
                <div class="address-card">
                    <div class="address-card-top">
                        <span class="address-icon-tile"><i class="fas {{ $addrIcon }}"></i></span>
                        <div class="address-name-row">
                            <span class="address-name">{{ $address->name }}</span>
                            @if($address->is_default)
                                <span class="default-pill">Default</span>
                            @endif
                        </div>
                    </div>
                    <p class="address-line mb-0">{{ $address->address }}, {{ $address->city }}, {{ $address->state }} - {{ $address->pincode }}</p>
                    <p class="address-line mb-0"><i class="fas fa-phone me-1"></i>{{ $address->phone }}</p>

                    <div class="address-divider"></div>

                    <div class="address-actions">
                        @if(!$address->is_default)
                            <button class="address-action-link set-default-btn" data-id="{{ $address->id }}">
                                <i class="fas fa-star"></i> Set default
                            </button>
                        @endif
                        <button class="address-action-link edit-address"
                                data-id="{{ $address->id }}"
                                data-name="{{ $address->name }}"
                                data-address="{{ $address->address }}"
                                data-city="{{ $address->city }}"
                                data-state="{{ $address->state }}"
                                data-pincode="{{ $address->pincode }}"
                                data-phone="{{ $address->phone }}"
                                data-latitude="{{ $address->latitude }}"
                                data-longitude="{{ $address->longitude }}">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button class="address-action-link danger delete-address" data-id="{{ $address->id }}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<!-- Add/Edit Address Modal -->
<div class="modal fade" id="addressModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Add New Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addressForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <input type="hidden" name="address_id" id="addressId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address Name</label>
                            <select name="name" id="addressName" class="form-select" required>
                                <option value="Home">Home</option>
                                <option value="Work">Work</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" id="addressPhone" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Full Address</label>
                            <textarea name="address" id="addressText" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="addressCity" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" id="addressState" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="pincode" id="addressPincode" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <input type="hidden" name="latitude" id="addressLatitude" required>
                            <input type="hidden" name="longitude" id="addressLongitude" required>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" id="detectLocationBtn">
                                <i class="fas fa-location-crosshairs me-1"></i>Detect exact location
                            </button>
                            <span class="small text-muted ms-2" id="pinStatus">Exact geo location required.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Save Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger">Delete Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this address?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0">
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
    let currentDeleteId = null;
    
    // Set Default Address
    document.querySelectorAll('.set-default-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            
            try {
                const response = await fetch(`/customer/addresses/${id}/default`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });
    });
    
    // Edit Address
    document.querySelectorAll('.edit-address').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const address = this.dataset.address;
            const city = this.dataset.city;
            const state = this.dataset.state;
            const pincode = this.dataset.pincode;
            const phone = this.dataset.phone;
            const latitude = this.dataset.latitude;
            const longitude = this.dataset.longitude;
            
            document.getElementById('modalTitle').textContent = 'Edit Address';
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('addressId').value = id;
            document.getElementById('addressName').value = name;
            document.getElementById('addressText').value = address;
            document.getElementById('addressCity').value = city;
            document.getElementById('addressState').value = state;
            document.getElementById('addressPincode').value = pincode;
            document.getElementById('addressPhone').value = phone;
            document.getElementById('addressLatitude').value = latitude || '';
            document.getElementById('addressLongitude').value = longitude || '';
            updatePinStatus();
            
            const form = document.getElementById('addressForm');
            form.action = `/customer/addresses/${id}`;
            
            new bootstrap.Modal(document.getElementById('addressModal')).show();
        });
    });
    
    // Delete Address
    document.querySelectorAll('.delete-address').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const form = document.getElementById('deleteForm');
            form.action = `/customer/addresses/${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });
    
    // Reset Modal on close
    document.getElementById('addressModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('modalTitle').textContent = 'Add New Address';
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('addressId').value = '';
        document.getElementById('addressForm').action = '{{ route("customer.addresses.store") }}';
        document.getElementById('addressForm').reset();
        document.getElementById('addressName').value = 'Home';
        document.getElementById('addressLatitude').value = '';
        document.getElementById('addressLongitude').value = '';
        updatePinStatus();
    });

    document.getElementById('detectLocationBtn').addEventListener('click', function() {
        if (!navigator.geolocation) {
            alert('Geo location is not available in this browser.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            position => {
                document.getElementById('addressLatitude').value = position.coords.latitude;
                document.getElementById('addressLongitude').value = position.coords.longitude;
                updatePinStatus();
            },
            () => alert('Unable to detect location. Please allow location permission.')
        );
    });

    document.getElementById('addressForm').addEventListener('submit', function(event) {
        if (!document.getElementById('addressLatitude').value || !document.getElementById('addressLongitude').value) {
            event.preventDefault();
            alert('Please detect exact geo location before saving this address.');
        }
    });

    function updatePinStatus() {
        const lat = document.getElementById('addressLatitude').value;
        const lng = document.getElementById('addressLongitude').value;
        document.getElementById('pinStatus').textContent = lat && lng
            ? `Pinned: ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`
            : 'Exact geo location required.';
    }
</script>
@endsection
