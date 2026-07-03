@extends('layouts.app')

@section('title', 'My Addresses')

@section('styles')
<style>
    .address-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 16px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s;
        position: relative;
    }
    
    .address-card:hover {
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .address-card.default {
        border: 2px solid #FF6B35;
        background: #FFF7F5;
    }
    
    .default-badge {
        position: absolute;
        top: 20px;
        right: 20px;
        background: #FF6B35;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .address-type {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        background: #e2e8f0;
        color: #475569;
        margin-bottom: 12px;
    }
    
    .address-type.home { background: #DBEAFE; color: #1E40AF; }
    .address-type.work { background: #D1FAE5; color: #065F46; }
    .address-type.other { background: #FEF3C7; color: #92400E; }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        margin-top: 16px;
    }
    
    .btn-icon {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 13px;
    }
    
    .add-address-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 56px;
        height: 56px;
        border-radius: 28px;
        background: #FF6B35;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(255,107,53,0.4);
        cursor: pointer;
        transition: all 0.3s;
        z-index: 100;
        border: none;
    }
    
    .add-address-btn:hover {
        transform: scale(1.1);
        background: #E55A2B;
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
                <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addressModal">
                    <i class="fas fa-plus me-2"></i> Add New
                </button>
            </div>
            
            <!-- Success/Error Messages -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            
            <!-- Addresses List -->
            @if($addresses->isEmpty())
                <div class="card shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-map-marker-alt fa-4x text-muted mb-3"></i>
                        <h4>No Addresses Saved</h4>
                        <p class="text-muted">Add your first delivery address</p>
                        <button class="btn btn-primary rounded-pill mt-3" data-bs-toggle="modal" data-bs-target="#addressModal">
                            <i class="fas fa-plus me-2"></i> Add Address
                        </button>
                    </div>
                </div>
            @else
                @foreach($addresses as $address)
                <div class="address-card {{ $address->is_default ? 'default' : '' }}">
                    @if($address->is_default)
                        <div class="default-badge">
                            <i class="fas fa-star me-1"></i> Default
                        </div>
                    @endif
                    
                    <span class="address-type {{ $address->name == 'Home' ? 'home' : ($address->name == 'Work' ? 'work' : 'other') }}">
                        <i class="fas fa-{{ $address->name == 'Home' ? 'home' : ($address->name == 'Work' ? 'briefcase' : 'map-pin') }} me-1"></i>
                        {{ $address->name }}
                    </span>
                    
                    <h6 class="fw-bold mb-2">{{ auth()->user()->name }}</h6>
                    <p class="mb-1 text-muted">{{ $address->address }}</p>
                    <p class="mb-1 text-muted">{{ $address->city }}, {{ $address->state }} - {{ $address->pincode }}</p>
                    <p class="mb-0 text-muted"><i class="fas fa-phone me-2"></i>{{ $address->phone }}</p>
                    
                    <div class="action-buttons">
                        @if(!$address->is_default)
                            <button class="btn btn-sm btn-outline-success btn-icon set-default-btn" data-id="{{ $address->id }}">
                                <i class="fas fa-star me-1"></i> Set Default
                            </button>
                        @endif
                        <button class="btn btn-sm btn-outline-primary btn-icon edit-address" 
                                data-id="{{ $address->id }}"
                                data-name="{{ $address->name }}"
                                data-address="{{ $address->address }}"
                                data-city="{{ $address->city }}"
                                data-state="{{ $address->state }}"
                                data-pincode="{{ $address->pincode }}"
                                data-phone="{{ $address->phone }}"
                                data-latitude="{{ $address->latitude }}"
                                data-longitude="{{ $address->longitude }}">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-icon delete-address" data-id="{{ $address->id }}">
                            <i class="fas fa-trash me-1"></i> Delete
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

<!-- Floating Action Button -->
<button class="add-address-btn" data-bs-toggle="modal" data-bs-target="#addressModal">
    <i class="fas fa-plus fa-lg"></i>
</button>

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
