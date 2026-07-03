<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Partner with {{ $appName ?? config('app.name') }} - Grow Your Business</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        
        .partner-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .partner-card { background: white; border-radius: 32px; max-width: 900px; width: 100%; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        .partner-header { background: linear-gradient(135deg, #1C1C1C 0%, #2D2D2D 100%); padding: 40px; text-align: center; color: white; }
        .partner-header h1 { font-size: 32px; font-weight: 800; margin-bottom: 10px; }
        .partner-header p { opacity: 0.8; }
        .partner-body { padding: 40px; }
        
        .partner-type-selector { display: flex; gap: 20px; margin-bottom: 30px; cursor: pointer; }
        .partner-type-card { flex: 1; border: 2px solid #E8E8E8; border-radius: 20px; padding: 30px; text-align: center; transition: all 0.3s; cursor: pointer; }
        .partner-type-card:hover { border-color: #EF4F5F; background: #FFF5F5; transform: translateY(-5px); }
        .partner-type-card.active { border-color: #EF4F5F; background: #FFF5F5; }
        .partner-type-card i { font-size: 48px; margin-bottom: 15px; color: #EF4F5F; }
        .partner-type-card h3 { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
        .partner-type-card p { color: #696969; font-size: 14px; margin: 0; }
        
        .form-step { display: none; animation: fadeIn 0.3s ease; }
        .form-step.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .step-indicator { display: flex; justify-content: space-between; margin-bottom: 40px; position: relative; }
        .step-indicator::before { content: ''; position: absolute; top: 20px; left: 10%; right: 10%; height: 2px; background: #E8E8E8; z-index: 0; }
        .step { text-align: center; flex: 1; position: relative; z-index: 1; background: white; }
        .step-number { width: 40px; height: 40px; background: #F0F0F0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; margin-bottom: 8px; transition: all 0.3s; }
        .step.active .step-number { background: #EF4F5F; color: white; }
        .step.completed .step-number { background: #10B981; color: white; }
        .step-label { font-size: 12px; font-weight: 500; color: #696969; }
        .step.active .step-label { color: #EF4F5F; font-weight: 600; }
        
        .form-control, .form-select { border: 1.5px solid #E8E8E8; border-radius: 12px; padding: 12px 16px; transition: all 0.3s; }
        .form-control:focus, .form-select:focus { border-color: #EF4F5F; box-shadow: 0 0 0 3px rgba(239,79,95,0.1); }
        .form-label { font-weight: 600; margin-bottom: 8px; color: #1C1C1C; }
        .required::after { content: '*'; color: #EF4F5F; margin-left: 4px; }
        
        .btn-partner { background: linear-gradient(135deg, #EF4F5F, #FF8C42); border: none; padding: 14px 32px; font-weight: 700; border-radius: 50px; transition: all 0.3s; color: white; }
        .btn-partner:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(239,79,95,0.3); }
        .btn-outline-partner { border: 2px solid #EF4F5F; background: transparent; color: #EF4F5F; padding: 12px 28px; font-weight: 600; border-radius: 50px; transition: all 0.3s; }
        .btn-outline-partner:hover { background: #EF4F5F; color: white; }
        
        .file-upload { border: 2px dashed #E8E8E8; border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .file-upload:hover { border-color: #EF4F5F; background: #FFF5F5; }
        .file-upload i { font-size: 32px; color: #EF4F5F; margin-bottom: 10px; }
        .file-name { font-size: 12px; color: #696969; margin-top: 5px; }
        
        @media (max-width: 768px) {
            .partner-body { padding: 24px; }
            .partner-type-card { padding: 20px; }
            .partner-type-card i { font-size: 32px; }
            .step-label { font-size: 10px; }
        }
    </style>
@include('partials.public-blade-polish')
</head>
<body>
    <div class="partner-container">
        <div class="partner-card">
            <div class="partner-header">
                <h1>Partner with {{ $appName ?? config('app.name') }}</h1>
                <p>Join India's fastest-growing food delivery platform</p>
            </div>
            <div class="partner-body">
                <!-- Partner Type Selection -->
                <div class="partner-type-selector" id="partnerTypeSelector">
                    <div class="partner-type-card" data-type="restaurant">
                        <i class="fas fa-store"></i>
                        <h3>Restaurant Partner</h3>
                        <p>List your restaurant & reach thousands of customers</p>
                    </div>
                    <div class="partner-type-card" data-type="driver">
                        <i class="fas fa-motorcycle"></i>
                        <h3>Delivery Partner</h3>
                        <p>Earn money by delivering on your own schedule</p>
                    </div>
                </div>

                <!-- Step Indicator -->
                <div class="step-indicator" id="stepIndicator" style="display: none;">
                    <div class="step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label">Basic Info</div>
                    </div>
                    <div class="step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label">Business Details</div>
                    </div>
                    <div class="step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label">Documents</div>
                    </div>
                    <div class="step" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-label">Bank Details</div>
                    </div>
                </div>

                <!-- Restaurant Registration Form -->
                <form id="restaurantForm" class="form-step" style="display: none;">
                    <input type="hidden" name="partner_type" value="restaurant">
                    
                    <!-- Step 1: Basic Info -->
                    <div class="step-content" data-step="1">
                        <h4 class="mb-4">Restaurant Basic Information</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Restaurant Name</label>
                                <input type="text" class="form-control" name="business_name" required placeholder="e.g., Pizza Hut">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Restaurant Email</label>
                                <input type="email" class="form-control" name="business_email" required placeholder="restaurant@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Phone Number</label>
                                <input type="tel" class="form-control" name="business_phone" required placeholder="+91 XXXXXXXXXX">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">City</label>
                                <select class="form-select" name="city" required>
                                    <option value="">Select City</option>
                                    <option>Mumbai</option>
                                    <option>Delhi</option>
                                    <option>Bangalore</option>
                                    <option>Hyderabad</option>
                                    <option>Chennai</option>
                                    <option>Kolkata</option>
                                    <option>Pune</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label required">Full Address</label>
                                <textarea class="form-control" name="address" rows="2" required placeholder="Shop No., Building, Street, Landmark"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control" name="pincode" placeholder="6 digits">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Cuisine Type</label>
                                <select class="form-select" name="cuisine" multiple>
                                    <option>North Indian</option>
                                    <option>South Indian</option>
                                    <option>Chinese</option>
                                    <option>Italian</option>
                                    <option>Fast Food</option>
                                    <option>Bakery</option>
                                    <option>Beverages</option>
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch p-3 rounded-3" style="background:#F8F8F8;">
                                    <input class="form-check-input" type="checkbox" name="is_pure_veg" id="partnerPureVeg" value="1">
                                    <label class="form-check-label fw-semibold" for="partnerPureVeg">This is a pure veg restaurant</label>
                                    <div class="small text-muted">Pure veg restaurants will not be allowed to create egg or non-veg items.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Contact Person Details -->
                    <div class="step-content" data-step="2" style="display: none;">
                        <h4 class="mb-4">Contact Person Details</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Contact Person Name</label>
                                <input type="text" class="form-control" name="contact_name" required placeholder="Full name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Designation</label>
                                <input type="text" class="form-control" name="contact_designation" required placeholder="Owner/Manager">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Contact Email</label>
                                <input type="email" class="form-control" name="contact_email" required placeholder="contact@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Contact Phone</label>
                                <input type="tel" class="form-control" name="contact_phone" required placeholder="+91 XXXXXXXXXX">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Create Password</label>
                                <input type="password" class="form-control" name="password" required placeholder="Min 6 characters">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Confirm Password</label>
                                <input type="password" class="form-control" name="password_confirmation" required placeholder="Confirm password">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Documents -->
                    <div class="step-content" data-step="3" style="display: none;">
                        <h4 class="mb-4">Upload Documents</h4>
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="file-upload" onclick="document.getElementById('gstFile').click()">
                                    <i class="fas fa-file-invoice"></i>
                                    <p class="mb-0 fw-semibold">GST Certificate</p>
                                    <p class="small text-muted mb-0">Upload GST certificate (PDF/Image, max 5MB)</p>
                                    <div class="file-name" id="gstFileName">No file chosen</div>
                                    <input type="file" id="gstFile" name="gst_certificate" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="file-upload" onclick="document.getElementById('fssaiFile').click()">
                                    <i class="fas fa-certificate"></i>
                                    <p class="mb-0 fw-semibold">FSSAI License</p>
                                    <p class="small text-muted mb-0">Upload FSSAI license (PDF/Image, max 5MB)</p>
                                    <div class="file-name" id="fssaiFileName">No file chosen</div>
                                    <input type="file" id="fssaiFile" name="fssai_license" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Bank Details -->
                    <div class="step-content" data-step="4" style="display: none;">
                        <h4 class="mb-4">Bank Account Details</h4>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" name="bank_holder_name" placeholder="As per bank records">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="bank_account_number" placeholder="Bank account number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Account Number</label>
                                <input type="text" class="form-control" name="bank_account_number_confirm" placeholder="Confirm account number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IFSC Code</label>
                                <input type="text" class="form-control" name="bank_ifsc" placeholder="IFSC code">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name" placeholder="Bank name">
                            </div>
                            <div class="col-12">
                                <textarea class="form-control" name="bank_details" rows="2" placeholder="Additional bank details (UPI ID, etc.)"></textarea>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Driver Registration Form -->
                <form id="driverForm" class="form-step" style="display: none;">
                    <input type="hidden" name="partner_type" value="driver">
                    
                    <!-- Step 1: Personal Info -->
                    <div class="step-content" data-step="1">
                        <h4 class="mb-4">Personal Information</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required placeholder="As per ID proof">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Email Address</label>
                                <input type="email" class="form-control" name="email" required placeholder="driver@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" required placeholder="+91 XXXXXXXXXX">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">City</label>
                                <select class="form-select" name="city" required>
                                    <option value="">Select City</option>
                                    <option>Mumbai</option>
                                    <option>Delhi</option>
                                    <option>Bangalore</option>
                                    <option>Hyderabad</option>
                                    <option>Chennai</option>
                                    <option>Kolkata</option>
                                    <option>Pune</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label required">Residential Address</label>
                                <textarea class="form-control" name="address" rows="2" required placeholder="Complete address"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Service Area</label>
                                <select id="driverAreaSelect" name="area_id" class="form-select" required>
                                    <option value="">Select Delivery Area</option>
                                    @foreach($deliveryAreas as $area)
                                        <option value="{{ $area->id }}">{{ $area->name }} ({{ $area->radius_km }} km)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Locate Your Area</label>
                                <div class="d-flex gap-2 mb-3">
                                    <button type="button" class="btn btn-outline-partner" id="detectLocationBtn">
                                        <i class="fas fa-location-arrow me-2"></i> Detect My Location
                                    </button>
                                    <button type="button" class="btn btn-outline-partner" id="resetLocationBtn">
                                        <i class="fas fa-map-pin me-2"></i> Reset Location
                                    </button>
                                </div>
                                <div id="driverMap" style="height: 320px; border-radius: 16px; border: 1px solid #e8e8e8;"></div>
                                <input type="hidden" name="latitude" id="driverLatitude">
                                <input type="hidden" name="longitude" id="driverLongitude">
                                <small class="text-muted">Use the map to pin your location; the nearest delivery area will be selected automatically.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Vehicle & License Details -->
                    <div class="step-content" data-step="2" style="display: none;">
                        <h4 class="mb-4">Vehicle & License Details</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Vehicle Type</label>
                                <select class="form-select" name="vehicle_type" required>
                                    <option value="">Select Vehicle</option>
                                    <option value="bike">Bike</option>
                                    <option value="scooter">Scooter</option>
                                    <option value="car">Car</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Vehicle Number</label>
                                <input type="text" class="form-control" name="vehicle_number" required placeholder="MH 01 AB 1234">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">License Number</label>
                                <input type="text" class="form-control" name="license_number" required placeholder="Driving license number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">License Expiry Date</label>
                                <input type="date" class="form-control" name="license_expiry" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Create Password</label>
                                <input type="password" class="form-control" name="password" required placeholder="Min 6 characters">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Confirm Password</label>
                                <input type="password" class="form-control" name="password_confirmation" required placeholder="Confirm password">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Documents -->
                    <div class="step-content" data-step="3" style="display: none;">
                        <h4 class="mb-4">Upload Documents</h4>
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="file-upload" onclick="document.getElementById('licenseFile').click()">
                                    <i class="fas fa-id-card"></i>
                                    <p class="mb-0 fw-semibold">Driving License</p>
                                    <p class="small text-muted mb-0">Upload front & back of license (PDF/Image, max 5MB)</p>
                                    <div class="file-name" id="licenseFileName">No file chosen</div>
                                    <input type="file" id="licenseFile" name="license_document" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="file-upload" onclick="document.getElementById('aadharFile').click()">
                                    <i class="fas fa-id-card"></i>
                                    <p class="mb-0 fw-semibold">Aadhar Card</p>
                                    <p class="small text-muted mb-0">Upload Aadhar card (PDF/Image, max 5MB)</p>
                                    <div class="file-name" id="aadharFileName">No file chosen</div>
                                    <input type="file" id="aadharFile" name="aadhar_card" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Bank Details -->
                    <div class="step-content" data-step="4" style="display: none;">
                        <h4 class="mb-4">Bank Account Details</h4>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" name="bank_holder_name" placeholder="As per bank records">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="bank_account_number" placeholder="Bank account number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Account Number</label>
                                <input type="text" class="form-control" name="bank_account_number_confirm" placeholder="Confirm account number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IFSC Code</label>
                                <input type="text" class="form-control" name="bank_ifsc" placeholder="IFSC code">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name" placeholder="Bank name">
                            </div>
                            <div class="col-12">
                                <label class="form-label">UPI ID (Optional)</label>
                                <input type="text" class="form-control" name="upi_id" placeholder="yourname@okhdfcbank">
                            </div>
                            <div class="col-12">
                                <textarea class="form-control" name="bank_details" rows="2" placeholder="Additional bank details"></textarea>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="terms" id="driverTerms" value="yes" required>
                                    <label class="form-check-label" for="driverTerms">
                                        I agree to the <a href="/terms" target="_blank">Terms & Conditions</a>.
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="terms" id="restaurantTerms" value="yes" required>
                                    <label class="form-check-label" for="restaurantTerms">
                                        I agree to the <a href="/terms" target="_blank">Terms & Conditions</a>.
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Form Navigation Buttons -->
                <div class="d-flex justify-content-between mt-4 pt-3" id="formButtons" style="display: none;">
                    <button type="button" class="btn btn-outline-partner" id="prevBtn" style="display: none;">
                        <i class="fas fa-arrow-left me-2"></i> Previous
                    </button>
                    <div>
                        <button type="button" class="btn btn-outline-partner" data-bs-dismiss="modal" id="cancelBtn">Cancel</button>
                        <button type="button" class="btn btn-partner" id="nextBtn">
                            Next <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                        <button type="button" class="btn btn-partner" id="submitBtn" style="display: none;">
                            Submit Application <i class="fas fa-check ms-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.google-maps-shim')
    <script>
        // Global variables
        let selectedType = null;
        let currentStep = 1;
        let totalSteps = 4;
        let driverMap = null;
        let driverMarker = null;
        let driverCircle = null;

        // DOM Elements
        const typeCards = document.querySelectorAll('.partner-type-card');
        const restaurantForm = document.getElementById('restaurantForm');
        const driverForm = document.getElementById('driverForm');
        const stepIndicator = document.getElementById('stepIndicator');
        const formButtons = document.getElementById('formButtons');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        const driverAreaSelect = document.getElementById('driverAreaSelect');
        const driverLatitudeInput = document.getElementById('driverLatitude');
        const driverLongitudeInput = document.getElementById('driverLongitude');
        const detectLocationBtn = document.getElementById('detectLocationBtn');
        const resetLocationBtn = document.getElementById('resetLocationBtn');
        const deliveryAreas = @json($deliveryAreas ?? []);
        
        // Partner Type Selection
        typeCards.forEach(card => {
            card.addEventListener('click', function() {
                typeCards.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                selectedType = this.dataset.type;
                
                // Show forms based on selection
                if (selectedType === 'restaurant') {
                    restaurantForm.style.display = 'block';
                    driverForm.style.display = 'none';
                } else {
                    restaurantForm.style.display = 'none';
                    driverForm.style.display = 'block';
                    setTimeout(() => driverMap?.invalidateSize(), 300);
                }
                
                // Show step indicator and buttons
                stepIndicator.style.display = 'flex';
                formButtons.style.display = 'flex';
                
                // Reset to step 1
                currentStep = 1;
                updateStepDisplay();
            });
        });
        
        // File upload handlers
        function setupFileUpload(inputId, displayId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'No file chosen';
                    document.getElementById(displayId).innerText = fileName;
                });
            }
        }
        
        setupFileUpload('gstFile', 'gstFileName');
        setupFileUpload('fssaiFile', 'fssaiFileName');
        setupFileUpload('licenseFile', 'licenseFileName');
        setupFileUpload('aadharFile', 'aadharFileName');

        function initializeDriverMap() {
            const mapContainer = document.getElementById('driverMap');
            if (!mapContainer || typeof L === 'undefined') {
                return;
            }

            driverMap = L.map(mapContainer).setView([20.5937, 78.9629], 5);

            driverMap.on('click', function(e) {
                setDriverLocation(e.latlng.lat, e.latlng.lng);
            });

            if (driverLatitudeInput.value && driverLongitudeInput.value) {
                setDriverLocation(parseFloat(driverLatitudeInput.value), parseFloat(driverLongitudeInput.value));
            }
        }

        function setDriverLocation(lat, lng) {
            driverLatitudeInput.value = lat;
            driverLongitudeInput.value = lng;

            if (!driverMarker) {
                driverMarker = L.marker([lat, lng], { draggable: true }).addTo(driverMap);
                driverMarker.on('dragend', function(event) {
                    const position = event.target.getLatLng();
                    setDriverLocation(position.lat, position.lng);
                });
            } else {
                driverMarker.setLatLng([lat, lng]);
            }

            if (driverCircle) {
                driverMap.removeLayer(driverCircle);
            }

            driverMap.setView([lat, lng], 12);
            autoSelectNearestArea(lat, lng);
        }

        function autoSelectNearestArea(lat, lng) {
            if (!deliveryAreas.length) {
                return;
            }

            let closest = null;
            let closestDistance = Infinity;

            deliveryAreas.forEach(area => {
                const distance = getDistance(lat, lng, parseFloat(area.latitude), parseFloat(area.longitude));
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closest = area;
                }
            });

            if (closest) {
                driverAreaSelect.value = closest.id;
                if (driverCircle) {
                    driverMap.removeLayer(driverCircle);
                }
                driverCircle = L.circle([closest.latitude, closest.longitude], {
                    radius: closest.radius_km * 1000,
                    color: '#007bff',
                    fillOpacity: 0.08,
                }).addTo(driverMap);
            }
        }

        function getDistance(lat1, lng1, lat2, lng2) {
            const toRad = x => x * Math.PI / 180;
            const R = 6371;
            const dLat = toRad(lat2 - lat1);
            const dLng = toRad(lng2 - lng1);
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                Math.sin(dLng / 2) * Math.sin(dLng / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        detectLocationBtn?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                showToast('Geolocation is not supported by your browser.', 'error');
                return;
            }

            navigator.geolocation.getCurrentPosition(position => {
                setDriverLocation(position.coords.latitude, position.coords.longitude);
            }, () => {
                showToast('Unable to detect location. Please use the map pin instead.', 'error');
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
            });
        });

        resetLocationBtn?.addEventListener('click', () => {
            if (driverMap) {
                driverMap.setView([20.5937, 78.9629], 5);
            }
            if (driverMarker) {
                driverMap.removeLayer(driverMarker);
                driverMarker = null;
            }
            if (driverCircle) {
                driverMap.removeLayer(driverCircle);
                driverCircle = null;
            }
            driverLatitudeInput.value = '';
            driverLongitudeInput.value = '';
            driverAreaSelect.value = '';
        });

        initializeDriverMap();
        
        // Update step display
        function updateStepDisplay() {
            const activeForm = selectedType === 'restaurant' ? restaurantForm : driverForm;
            const steps = activeForm.querySelectorAll('.step-content');
            
            steps.forEach((step, index) => {
                if (index + 1 === currentStep) {
                    step.style.display = 'block';
                } else {
                    step.style.display = 'none';
                }
            });
            
            // Update step indicators
            const stepElements = document.querySelectorAll('.step');
            stepElements.forEach((step, index) => {
                if (index + 1 === currentStep) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else if (index + 1 < currentStep) {
                    step.classList.remove('active');
                    step.classList.add('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            });
            
            // Update buttons
            prevBtn.style.display = currentStep === 1 ? 'none' : 'inline-block';
            
            if (currentStep === totalSteps) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'inline-block';
            } else {
                nextBtn.style.display = 'inline-block';
                submitBtn.style.display = 'none';
            }
        }
        
        // Validate current step
        function validateCurrentStep() {
            const activeForm = selectedType === 'restaurant' ? restaurantForm : driverForm;
            const currentStepContent = activeForm.querySelector(`.step-content[data-step="${currentStep}"]`);
            const requiredFields = currentStepContent.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                const invalid = field.type === 'checkbox' ? !field.checked : !field.value.trim();
                if (invalid) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Password validation for step 2
            if (currentStep === 2) {
                const password = activeForm.querySelector('input[name="password"]');
                const confirmPassword = activeForm.querySelector('input[name="password_confirmation"]');
                if (password && confirmPassword && password.value !== confirmPassword.value) {
                    showToast('Passwords do not match!', 'error');
                    isValid = false;
                }
                if (password && password.value.length < 6) {
                    showToast('Password must be at least 6 characters!', 'error');
                    isValid = false;
                }
            }
            
            // Bank account validation for step 4
            if (currentStep === 4) {
                const accountNumber = activeForm.querySelector('input[name="bank_account_number"]');
                const confirmAccount = activeForm.querySelector('input[name="bank_account_number_confirm"]');
                if (accountNumber && confirmAccount && accountNumber.value !== confirmAccount.value) {
                    showToast('Bank account numbers do not match!', 'error');
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        // Next button click
        nextBtn.addEventListener('click', () => {
            if (validateCurrentStep()) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateStepDisplay();
                }
            }
        });
        
        // Previous button click
        prevBtn.addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                updateStepDisplay();
            }
        });
        
        // Submit form
        submitBtn.addEventListener('click', async () => {
            if (!validateCurrentStep()) {
                return;
            }
            
            const activeForm = selectedType === 'restaurant' ? restaurantForm : driverForm;
            const formData = new FormData();
            
            // Collect all form fields
            const inputs = activeForm.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (!input.name || input.type === 'file') return;
                if (input.type === 'checkbox' && !input.checked) return;
                if (!input.value) return;

                if (input.name === 'cuisine' && input.multiple) {
                    const selected = Array.from(input.selectedOptions).map(opt => opt.value);
                    formData.append(input.name, JSON.stringify(selected));
                } else {
                    formData.append(input.name, input.value);
                }
            });
            
            // Add files
            const fileInputs = activeForm.querySelectorAll('input[type="file"]');
            fileInputs.forEach(fileInput => {
                if (fileInput.files[0]) {
                    formData.append(fileInput.name, fileInput.files[0]);
                }
            });
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
            
            try {
                const response = await fetch('/partner/register-submit', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                const data = await response.json().catch(() => null);
                
                if (!response.ok) {
                    const message = data?.message || (data?.errors ? Object.values(data.errors).flat().join(' ') : 'Something went wrong. Please try again.');
                    showToast(message, 'error');
                } else if (data?.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 2000);
                } else {
                    showToast(data?.message || 'Registration failed. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit Application <i class="fas fa-check ms-2"></i>';
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Something went wrong. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Application <i class="fas fa-check ms-2"></i>';
            }
        });
        
        // Toast function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.innerHTML = `<div class="d-flex align-items-center gap-2"><i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i><span>${message}</span></div>`;
            toast.style.cssText = `position: fixed; bottom: 20px; right: 20px; background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : type === 'warning' ? '#F59E0B' : '#3B82F6'}; color: white; padding: 12px 20px; border-radius: 12px; z-index: 10000; animation: slideInRight 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.15);`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } } @keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } } .is-invalid { border-color: #EF4444 !important; }`;
        document.head.appendChild(style);
    </script>
@include('partials.web-visit-tracker', ['panel' => 'partner'])
</body>
</html>




