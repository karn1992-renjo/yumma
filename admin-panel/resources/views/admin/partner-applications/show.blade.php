@extends('layouts.admin')

@section('title', 'Application #' . $application->application_number)
@section('header', 'Application Details')

@section('content')
@php
    $bankDetails = json_decode($application->bank_details ?? '[]', true);
    $bankDetails = is_array($bankDetails) ? $bankDetails : [];
    $meta = is_array($application->onboarding_meta ?? null) ? $application->onboarding_meta : [];
@endphp
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Application #{{ $application->application_number }}</h1>
            <p>Submitted on {{ $application->created_at->format('F d, Y \a\t h:i A') }}</p>
        </div>
        <div>
            <a href="{{ route('admin.partner-applications.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a href="{{ route('admin.partner-applications.edit', $application) }}" class="btn btn-primary">
                <i class="fas fa-pen me-2"></i> Edit
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Application Details -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Application Details</h5>
            </div>
            <div class="p-4">
                @if($application->partner_type == 'restaurant')
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Business Name</label>
                                <div class="fw-semibold">{{ $application->business_name }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Business Email</label>
                                <div>{{ $application->business_email }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Business Phone</label>
                                <div>{{ $application->business_phone }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Cuisine</label>
                                <div>{{ implode(', ', json_decode($application->cuisine ?? '[]', true)) }}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="text-muted small">Address</label>
                                <div>{{ $application->address }}, {{ $application->city }}, {{ $application->pincode }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Owner Name</label>
                                <div>{{ $meta['owner_name'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Delivery Area</label>
                                <div>{{ optional($application->deliveryArea)->name ?? ($meta['zone_name'] ?? 'Not selected') }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Zone Radius</label>
                                <div>{{ optional($application->deliveryArea)->radius_km ? optional($application->deliveryArea)->radius_km . ' km' : 'Not set' }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="fw-bold mb-3">Contact Person Details</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Contact Name</label>
                                <div>{{ $application->contact_name }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Designation</label>
                                <div>{{ $application->contact_designation }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Contact Email</label>
                                <div>{{ $application->contact_email }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Contact Phone</label>
                                <div>{{ $application->contact_phone }}</div>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold mb-3">Restaurant Workflow Details</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Opening Hours</label>
                                <div>{{ $meta['opening_time'] ?? 'N/A' }} - {{ $meta['closing_time'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Second Shift</label>
                                <div>{{ ($meta['secondary_opening_time'] ?? null) ? (($meta['secondary_opening_time'] ?? '') . ' - ' . ($meta['secondary_closing_time'] ?? '')) : 'Not set' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Weekly Off</label>
                                <div>{{ $meta['weekly_off'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Restaurant Categories</label>
                                <div>{{ $meta['restaurant_categories'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="text-muted small">Minimum Order</label>
                                <div>{{ $meta['minimum_order_value'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="text-muted small">Free Delivery Threshold</label>
                                <div>{{ $meta['free_delivery_threshold'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="text-muted small">Delivery Charges</label>
                                <div>{{ $meta['delivery_charges'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="text-muted small">Packaging Charge</label>
                                <div>{{ $meta['packaging_charge'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="text-muted small">GST Percentage</label>
                                <div>{{ $meta['gst_percentage'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="text-muted small">Handling Fee</label>
                                <div>{{ $meta['handling_fee'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Commission Preview</label>
                                <div>{{ $meta['commission_preview'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Payout Cycle</label>
                                <div>{{ $meta['payout_cycle'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="text-muted small">Menu Summary</label>
                                <div>{{ $meta['menu_summary'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Full Name</label>
                                <div class="fw-semibold">{{ $application->full_name }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Email</label>
                                <div>{{ $application->email }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Phone</label>
                                <div>{{ $application->phone }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">City</label>
                                <div>{{ $application->city }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Delivery Area</label>
                                <div>{{ optional($application->deliveryArea)->name ?? 'Not selected' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Zone Radius</label>
                                <div>{{ optional($application->deliveryArea)->radius_km ? optional($application->deliveryArea)->radius_km . ' km' : 'Not set' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Vehicle Type</label>
                                <div>{{ ucfirst($application->vehicle_type) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Date of Birth</label>
                                <div>{{ $meta['date_of_birth'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Gender</label>
                                <div>{{ $meta['gender'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Vehicle Number</label>
                                <div>{{ $application->vehicle_number }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">License Number</label>
                                <div>{{ $application->license_number }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Vehicle Model</label>
                                <div>{{ $meta['vehicle_model'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Fuel Type</label>
                                <div>{{ $meta['fuel_type'] ?? 'Not provided' }}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="text-muted small">Address</label>
                                <div>{{ $application->address }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Background Location</label>
                                <div>{{ !empty($meta['background_location_enabled']) ? 'Granted' : 'Not granted' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Notifications</label>
                                <div>{{ !empty($meta['notification_permission_enabled']) ? 'Granted' : 'Not granted' }}</div>
                            </div>
                        </div>
                    </div>
                @endif
                
                @if(!empty($bankDetails))
                    <hr>
                    <h6 class="fw-bold mb-3">Bank Details</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Account Holder</label>
                                <div>{{ $bankDetails['holder_name'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Bank Name</label>
                                <div>{{ $bankDetails['bank_name'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">Account Number</label>
                                <div>{{ $bankDetails['account_number'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">IFSC</label>
                                <div>{{ $bankDetails['ifsc'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">UPI ID</label>
                                <div>{{ $bankDetails['upi_id'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Documents -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Uploaded Documents</h5>
            </div>
            <div class="p-4">
                <div class="row">
                    @if($application->gst_certificate)
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center gap-2 p-3 border rounded">
                                <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                <div>
                                    <div class="small fw-semibold">GST Certificate</div>
                                    <a href="{{ Storage::url($application->gst_certificate) }}" target="_blank" class="small">View Document</a>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    @if($application->fssai_license)
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center gap-2 p-3 border rounded">
                                <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                <div>
                                    <div class="small fw-semibold">FSSAI License</div>
                                    <a href="{{ Storage::url($application->fssai_license) }}" target="_blank" class="small">View Document</a>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    @if($application->license_document)
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center gap-2 p-3 border rounded">
                                <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                <div>
                                    <div class="small fw-semibold">License Document</div>
                                    <a href="{{ Storage::url($application->license_document) }}" target="_blank" class="small">View Document</a>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    @foreach ([
                        'profile_photo' => 'Profile Photo',
                        'vehicle_image' => 'Vehicle Image',
                        'aadhar_card' => 'Aadhaar Card',
                        'pan_card' => 'PAN Card',
                        'vehicle_rc' => 'Vehicle RC',
                        'insurance_document' => 'Insurance',
                    ] as $key => $label)
                        @if($application->{$key})
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center gap-2 p-3 border rounded">
                                    <i class="fas fa-file-image fa-2x text-primary"></i>
                                    <div>
                                        <div class="small fw-semibold">{{ $label }}</div>
                                        <a href="{{ Storage::url($application->{$key}) }}" target="_blank" class="small">View Document</a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach

                    @foreach ([
                        'logo_image' => 'Logo Image',
                        'banner_image' => 'Banner Image',
                        'cover_image' => 'Cover Image',
                        'interior_image' => 'Interior Photo',
                        'food_image' => 'Food Photo',
                        'kitchen_image' => 'Kitchen Photo',
                        'bank_proof' => 'Bank Proof',
                        'shop_license' => 'Shop License',
                    ] as $key => $label)
                        @if(!empty($meta[$key]))
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center gap-2 p-3 border rounded">
                                    <i class="fas fa-file-image fa-2x text-primary"></i>
                                    <div>
                                        <div class="small fw-semibold">{{ $label }}</div>
                                        <a href="{{ Storage::url($meta[$key]) }}" target="_blank" class="small">View Document</a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                    
                    @if(
                        !$application->gst_certificate &&
                        !$application->fssai_license &&
                        !$application->license_document &&
                        !$application->profile_photo &&
                        !$application->vehicle_image &&
                        !$application->aadhar_card &&
                        !$application->pan_card &&
                        !$application->vehicle_rc &&
                        !$application->insurance_document &&
                        empty($meta['logo_image']) &&
                        empty($meta['banner_image']) &&
                        empty($meta['cover_image']) &&
                        empty($meta['interior_image']) &&
                        empty($meta['food_image']) &&
                        empty($meta['kitchen_image']) &&
                        empty($meta['bank_proof']) &&
                        empty($meta['shop_license'])
                    )
                        <div class="text-center text-muted py-3">No documents uploaded</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Status Card -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Application Status</h5>
            </div>
            <div class="p-4">
                <div class="text-center mb-3">
                    @if($application->status == 'pending')
                        <span class="badge badge-warning p-2 px-3" style="font-size: 14px;">Pending Review</span>
                    @elseif($application->status == 'approved')
                        <span class="badge badge-success p-2 px-3" style="font-size: 14px;">Approved</span>
                    @else
                        <span class="badge badge-danger p-2 px-3" style="font-size: 14px;">Rejected</span>
                    @endif
                </div>
                
                @if($application->reviewed_at)
                    <hr>
                    <div class="mb-2">
                        <label class="text-muted small">Reviewed On</label>
                        <div>{{ $application->reviewed_at->format('F d, Y \a\t h:i A') }}</div>
                    </div>
                    <div class="mb-2">
                        <label class="text-muted small">Reviewed By</label>
                        <div>{{ $application->reviewer->name ?? 'N/A' }}</div>
                    </div>
                @endif
                
                @if($application->admin_notes)
                    <hr>
                    <div class="mb-2">
                        <label class="text-muted small">Admin Notes</label>
                        <div class="p-2 bg-light rounded mt-1">{{ $application->admin_notes }}</div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Action Buttons (if pending) -->
        @if($application->status == 'pending')
            <div class="table-card">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Actions</h5>
                </div>
                <div class="p-4">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                            <i class="fas fa-check-circle me-2"></i> Approve Application
                        </button>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="fas fa-times-circle me-2"></i> Reject Application
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Approve Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.partner-applications.approve', $application->id) }}" method="POST">
                @csrf
                <div class="modal-body px-4">
                    <p>Are you sure you want to approve this application?</p>
                    <p class="text-muted small">This will create a user account for the partner. The partner will receive an email notification.</p>
                    <div class="mb-3">
                        <label class="form-label">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" class="form-control" rows="2" placeholder="Add any notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Reject Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.partner-applications.reject', $application->id) }}" method="POST">
                @csrf
                <div class="modal-body px-4">
                    <p>Are you sure you want to reject this application?</p>
                    <div class="mb-3">
                        <label class="form-label text-danger">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Please provide a reason for rejection..."></textarea>
                        <small class="text-muted">This reason will be sent to the applicant via email.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
