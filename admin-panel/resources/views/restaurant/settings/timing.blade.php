{{-- resources/views/restaurant/settings/timing.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Restaurant Timing Settings')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="mb-1">Restaurant Timing Settings</h1>
                <p class="text-muted mb-0">Configure daily operating hours, breaks, and order processing rules</p>
            </div>
            <div>
                <span id="currentStatus" class="badge {{ $restaurant->is_open ? 'bg-success' : 'bg-danger' }} p-3 fs-6">
                    <i class="fas {{ $restaurant->is_open ? 'fa-circle' : 'fa-circle' }} me-2"></i>
                    {{ $restaurant->is_open ? 'Currently Online' : 'Currently Offline' }}
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Weekly Operating Hours Section -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-calendar-week text-primary me-2"></i>
                            Weekly Operating Hours
                        </h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="copyTimingsBtn">
                                <i class="fas fa-copy me-1"></i> Copy Hours
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" id="weekdayApplyBtn">
                                <i class="fas fa-clock me-1"></i> Apply to Weekdays
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" id="resetToDefaultBtn">
                                <i class="fas fa-undo-alt me-1"></i> Reset to Default
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('restaurant.settings.timing.update') }}" method="POST" id="timingForm">
                        @csrf
                        @method('PUT')

                        <!-- Timezone Selection -->
                        <div class="row mb-4">
                            <div class="col-md-6 col-lg-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-globe me-1"></i> Timezone
                                </label>
                                <select name="timezone" class="form-select" id="timezoneSelect">
                                    <optgroup label="Common Timezones">
                                        <option value="Asia/Kolkata" {{ ($restaurant->timezone ?? 'Asia/Kolkata') == 'Asia/Kolkata' ? 'selected' : '' }}>India (IST)</option>
                                        <option value="Asia/Dubai" {{ ($restaurant->timezone ?? 'Asia/Kolkata') == 'Asia/Dubai' ? 'selected' : '' }}>UAE (GST)</option>
                                        <option value="America/New_York" {{ ($restaurant->timezone ?? 'Asia/Kolkata') == 'America/New_York' ? 'selected' : '' }}>USA (EST)</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-clock me-1"></i> Current Server Time
                                </label>
                                <div class="form-control bg-light" id="currentServerTime">
                                    Loading...
                                </div>
                            </div>
                        </div>

                        <!-- Weekly Timings Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="weeklyTimingsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 15%">Day</th>
                                        <th style="width: 8%" class="text-center">Status</th>
                                        <th style="width: 15%">Opening Time</th>
                                        <th style="width: 15%">Closing Time</th>
                                        <th style="width: 15%">Break Start</th>
                                        <th style="width: 15%">Break End</th>
                                        <th style="width: 10%" class="text-center">Preview</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $days = [
                                            'monday' => 'Monday',
                                            'tuesday' => 'Tuesday',
                                            'wednesday' => 'Wednesday',
                                            'thursday' => 'Thursday',
                                            'friday' => 'Friday',
                                            'saturday' => 'Saturday',
                                            'sunday' => 'Sunday'
                                        ];
                                        $weeklyTimings = $restaurant->weekly_timings ?? \App\Models\Restaurant::getDefaultWeeklyTimings();
                                        $today = strtolower(now()->format('l'));
                                    @endphp
                                    @foreach($days as $dayKey => $dayName)
                                        @php
                                            $timing = $weeklyTimings[$dayKey] ?? ['is_open' => true, 'open_time' => '09:00', 'close_time' => '22:00', 'break_start' => null, 'break_end' => null];
                                            $isToday = ($dayKey === $today);
                                        @endphp
                                        <tr id="row-{{ $dayKey }}" class="{{ $timing['is_open'] ? '' : 'table-secondary' }}">
                                            <td>
                                                <strong>{{ $dayName }}</strong>
                                                @if($isToday)
                                                    <span class="badge bg-primary ms-2">Today</span>
                                                @endif
                                                <input type="hidden" name="timings[{{ $dayKey }}][is_open]" value="0">
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-block">
                                                    <input type="checkbox" 
                                                           name="timings[{{ $dayKey }}][is_open]" 
                                                           value="1" 
                                                           class="form-check-input status-toggle"
                                                           data-day="{{ $dayKey }}"
                                                           id="toggle-{{ $dayKey }}"
                                                           {{ $timing['is_open'] ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="time" 
                                                       name="timings[{{ $dayKey }}][open_time]" 
                                                       class="form-control form-control-sm time-input open-time"
                                                       value="{{ $timing['open_time'] }}"
                                                       {{ !$timing['is_open'] ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="time" 
                                                       name="timings[{{ $dayKey }}][close_time]" 
                                                       class="form-control form-control-sm time-input close-time"
                                                       value="{{ $timing['close_time'] }}"
                                                       {{ !$timing['is_open'] ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="time" 
                                                       name="timings[{{ $dayKey }}][break_start]" 
                                                       class="form-control form-control-sm time-input break-start"
                                                       value="{{ $timing['break_start'] }}"
                                                       {{ !$timing['is_open'] ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="time" 
                                                       name="timings[{{ $dayKey }}][break_end]" 
                                                       class="form-control form-control-sm time-input break-end"
                                                       value="{{ $timing['break_end'] }}"
                                                       {{ !$timing['is_open'] ? 'disabled' : '' }}>
                                            </td>
                                            <td class="text-center">
                                                @if($timing['is_open'])
                                                    <span class="badge bg-info day-preview-badge">
                                                        {{ date('h:i A', strtotime($timing['open_time'])) }} - {{ date('h:i A', strtotime($timing['close_time'])) }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary">Closed</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <hr class="my-4">

                        <!-- Order Processing Settings -->
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-cogs text-primary me-2"></i>
                            Order Processing Settings
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="auto_accept_orders" class="form-check-input" id="autoAcceptOrders"
                                           value="1" {{ $restaurant->auto_accept_orders ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="autoAcceptOrders">
                                        Auto-accept Orders
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Order Processing Type</label>
                                <select name="order_processing_type" class="form-select">
                                    <option value="after_restaurant_accept" {{ (session('order_processing_type', 'after_restaurant_accept') == 'after_restaurant_accept') ? 'selected' : '' }}>
                                        Process after restaurant accepts order
                                    </option>
                                    <option value="only_if_driver_available" {{ (session('order_processing_type') == 'only_if_driver_available') ? 'selected' : '' }}>
                                        Only process if driver available
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn btn-light" id="cancelBtn">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-1"></i> Save All Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Manual Status Control -->
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-power-off text-primary me-2"></i>
                        Manual Status Control
                    </h5>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <div class="display-1 mb-3">
                            <i class="fas {{ $restaurant->is_open ? 'fa-store text-success' : 'fa-store-slash text-danger' }}"></i>
                        </div>
                        <h3 class="mb-2">{{ $restaurant->is_open ? 'Restaurant is Online' : 'Restaurant is Offline' }}</h3>
                        <p class="text-muted mb-0">
                            {{ $restaurant->is_open ? 'Accepting orders from customers' : 'Not accepting orders' }}
                        </p>
                    </div>

                    @if($restaurant->is_open)
                        <button type="button" class="btn btn-danger btn-lg w-100" id="goOfflineBtn">
                            <i class="fas fa-power-off me-2"></i> Go Offline
                        </button>
                    @else
                        <button type="button" class="btn btn-success btn-lg w-100" id="goOnlineBtn">
                            <i class="fas fa-power-off me-2"></i> Go Online
                        </button>
                    @endif

                    @if(!$restaurant->is_open && $restaurant->offline_reason)
                        <div class="alert alert-secondary mt-4 text-start small">
                            <strong><i class="fas fa-info-circle me-1"></i> Last Offline Reason:</strong><br>
                            {{ $restaurant->offline_reason['reason'] ?? 'N/A' }}
                            @if(isset($restaurant->offline_reason['sub_reason']))
                                <br><strong>Details:</strong> {{ $restaurant->offline_reason['sub_reason'] }}
                            @endif
                            <br><strong>Time:</strong> {{ \Carbon\Carbon::parse($restaurant->offline_reason['set_at'])->format('d M Y h:i A') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Live Status Preview -->
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Live Status Preview
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="display-6 mb-2" id="liveClock">--:--:--</div>
                        <div class="text-muted" id="currentDateDisplay"></div>
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar bg-info" id="dayProgress" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-calendar-day me-2"></i>
                                <strong>Today's Schedule</strong>
                            </div>
                            <span id="todayStatusBadge" class="badge bg-secondary">Loading...</span>
                        </div>
                        <hr class="my-2">
                        <div id="todaySchedule"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .form-switch .form-check-input {
        width: 2.5em;
        height: 1.25em;
        cursor: pointer;
    }
    .form-switch .form-check-input:checked {
        background-color: #198754;
        border-color: #198754;
    }
    .table-secondary {
        background-color: #f8f9fa;
    }
    .time-input:disabled {
        background-color: #e9ecef;
        opacity: 0.6;
    }
    #liveClock {
        font-family: 'Courier New', monospace;
        font-weight: bold;
        font-size: 2rem;
    }
    .modal-backdrop {
        z-index: 1040 !important;
    }
    .modal {
        z-index: 1050 !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ==================== Status Toggle ====================
    const statusToggles = document.querySelectorAll('.status-toggle');
    
    statusToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const isChecked = this.checked;
            const row = this.closest('tr');
            const dayName = row.querySelector('td:first strong').textContent;
            const timeInputs = row.querySelectorAll('.time-input');
            
            timeInputs.forEach(input => {
                input.disabled = !isChecked;
            });
            
            if (isChecked) {
                row.classList.remove('table-secondary');
                showToast('success', `${dayName} is now open`);
            } else {
                row.classList.add('table-secondary');
                showToast('info', `${dayName} is now closed`);
            }
        });
    });
    
    // ==================== Toast Notification ====================
    function showToast(icon, title) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        Toast.fire({ icon: icon, title: title });
    }
    
    // ==================== Live Clock ====================
    function updateLiveClock() {
        const now = new Date();
        const timezone = document.getElementById('timezoneSelect')?.value || 'Asia/Kolkata';
        
        try {
            const timeString = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).format(now);
            
            const dateString = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }).format(now);
            
            document.getElementById('liveClock').textContent = timeString;
            document.getElementById('currentDateDisplay').textContent = dateString;
            document.getElementById('currentServerTime').textContent = timeString;
            
            // Update progress
            const startOfDay = new Date(now);
            startOfDay.setHours(0, 0, 0, 0);
            const endOfDay = new Date(now);
            endOfDay.setHours(23, 59, 59, 999);
            const progress = ((now - startOfDay) / (endOfDay - startOfDay)) * 100;
            document.getElementById('dayProgress').style.width = progress + '%';
        } catch (e) {
            console.error('Clock error:', e);
        }
    }
    
    updateLiveClock();
    setInterval(updateLiveClock, 1000);
    
    // ==================== Go Offline Modal (Custom Modal to avoid Bootstrap issues) ====================
    const goOfflineBtn = document.getElementById('goOfflineBtn');
    if (goOfflineBtn) {
        goOfflineBtn.addEventListener('click', function() {
            // Create custom modal div
            const modalHtml = `
                <div id="offlineModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                    <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90%; overflow: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                        <form id="offlineReasonForm" action="{{ route('restaurant.settings.go-offline') }}" method="POST">
                            @csrf
                            <div style="padding: 20px; border-bottom: 1px solid #dee2e6; background: #dc3545; color: white; border-radius: 12px 12px 0 0;">
                                <h5 style="margin: 0;">
                                    <i class="fas fa-power-off me-2"></i> Go Offline
                                </h5>
                            </div>
                            <div style="padding: 20px;">
                                <div class="alert alert-warning" style="margin-bottom: 20px; padding: 12px; background: #fff3cd; border: 1px solid #ffecb5; border-radius: 8px;">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Going offline will hide your restaurant and prevent new orders.
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Reason for going offline *</label>
                                    <select name="reason" id="offlineReasonSelect" class="form-select" required style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px;">
                                        <option value="">Select a reason...</option>
                                        <option value="Technical Issue">🛠️ Technical Issue</option>
                                        <option value="Low Stock">📦 Low Stock / Out of Stock</option>
                                        <option value="Staff Shortage">👥 Staff Shortage</option>
                                        <option value="Maintenance">🔧 Maintenance / Renovation</option>
                                        <option value="Holiday">🎉 Holiday / Vacation</option>
                                        <option value="Weather Condition">🌧️ Weather Condition</option>
                                        <option value="Temporary Break">☕ Temporary Break</option>
                                        <option value="Other">📝 Other</option>
                                    </select>
                                </div>
                                
                                <div id="otherReasonDiv" style="margin-bottom: 20px; display: none;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Please specify</label>
                                    <textarea name="other_reason" class="form-control" rows="2" placeholder="Enter details..." style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px;"></textarea>
                                </div>
                                
                                <div class="form-check" style="margin-bottom: 20px;">
                                    <input type="checkbox" class="form-check-input" id="confirmOffline" style="margin-right: 8px;">
                                    <label class="form-check-label" for="confirmOffline">
                                        I confirm that I want to take my restaurant offline
                                    </label>
                                </div>
                            </div>
                            <div style="padding: 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px; background: #f8f9fa; border-radius: 0 0 12px 12px;">
                                <button type="button" id="closeOfflineModal" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                                <button type="submit" id="submitOfflineBtn" disabled style="padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 6px; cursor: pointer;">Go Offline</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            const modal = document.getElementById('offlineModal');
            const reasonSelect = document.getElementById('offlineReasonSelect');
            const otherReasonDiv = document.getElementById('otherReasonDiv');
            const confirmCheckbox = document.getElementById('confirmOffline');
            const submitBtn = document.getElementById('submitOfflineBtn');
            const closeBtn = document.getElementById('closeOfflineModal');
            
            reasonSelect.addEventListener('change', function() {
                otherReasonDiv.style.display = this.value === 'Other' ? 'block' : 'none';
            });
            
            confirmCheckbox.addEventListener('change', function() {
                submitBtn.disabled = !this.checked;
            });
            
            closeBtn.addEventListener('click', function() {
                modal.remove();
            });
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
            
            document.getElementById('offlineReasonForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const reason = reasonSelect.value;
                if (!reason) {
                    alert('Please select a reason');
                    return;
                }
                
                const formData = new FormData(this);
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                try {
                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'Restaurant is now offline',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to go offline'
                        });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Go Offline';
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Network error. Please try again.'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Go Offline';
                }
            });
        });
    }
    
    // ==================== Go Online ====================
    const goOnlineBtn = document.getElementById('goOnlineBtn');
    if (goOnlineBtn) {
        goOnlineBtn.addEventListener('click', async function() {
            const result = await Swal.fire({
                title: 'Go Online?',
                text: 'Your restaurant will be visible to customers and start accepting orders.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: 'Yes, go online'
            });
            
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Processing...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                
                try {
                    const response = await fetch('{{ route("restaurant.settings.go-online") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire('Success', 'Restaurant is now online', 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Failed to go online', 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'Network error. Please try again.', 'error');
                }
            }
        });
    }
    
    // ==================== Copy Timings (Custom Modal) ====================
    const copyBtn = document.getElementById('copyTimingsBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            const dayNames = { monday: 'Monday', tuesday: 'Tuesday', wednesday: 'Wednesday', thursday: 'Thursday', friday: 'Friday', saturday: 'Saturday', sunday: 'Sunday' };
            
            let checkboxesHtml = '';
            for (const day of days) {
                checkboxesHtml += `
                    <div style="margin-bottom: 8px;">
                        <input type="checkbox" class="copy-day-checkbox" value="${day}" id="copy_${day}" style="margin-right: 8px;">
                        <label for="copy_${day}">${dayNames[day]}</label>
                    </div>
                `;
            }
            
            const modalHtml = `
                <div id="copyModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                    <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; background: white;">
                        <div style="padding: 20px; border-bottom: 1px solid #dee2e6; background: #0d6efd; color: white; border-radius: 12px 12px 0 0;">
                            <h5 style="margin: 0;">Copy Timings</h5>
                        </div>
                        <div style="padding: 20px;">
                            <p style="margin-bottom: 15px;">Select source day:</p>
                            <select id="sourceDaySelect" class="form-select" style="width: 100%; padding: 8px; margin-bottom: 20px; border: 1px solid #ced4da; border-radius: 6px;">
                                ${days.map(day => `<option value="${day}">${dayNames[day]}</option>`).join('')}
                            </select>
                            <p style="margin-bottom: 15px;">Copy to:</p>
                            <div id="copyDaysList">${checkboxesHtml}</div>
                        </div>
                        <div style="padding: 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px;">
                            <button id="closeCopyModal" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                            <button id="confirmCopyBtn" style="padding: 8px 20px; background: #0d6efd; color: white; border: none; border-radius: 6px; cursor: pointer;">Copy</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            const modal = document.getElementById('copyModal');
            const closeBtn = document.getElementById('closeCopyModal');
            const confirmBtn = document.getElementById('confirmCopyBtn');
            
            closeBtn.addEventListener('click', () => modal.remove());
            modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
            
            confirmBtn.addEventListener('click', async () => {
                const sourceDay = document.getElementById('sourceDaySelect').value;
                const selectedDays = Array.from(document.querySelectorAll('.copy-day-checkbox:checked')).map(cb => cb.value);
                
                if (selectedDays.length === 0) {
                    alert('Please select at least one day to copy to');
                    return;
                }
                
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Copying...';
                
                try {
                    const response = await fetch('{{ route("restaurant.settings.copy-timings") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            from_day: sourceDay,
                            to_days: selectedDays
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire('Success', 'Timings copied successfully', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Failed to copy', 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'Network error', 'error');
                } finally {
                    modal.remove();
                }
            });
        });
    }
    
    // ==================== Apply Weekday Timings ====================
    const weekdayApplyBtn = document.getElementById('weekdayApplyBtn');
    if (weekdayApplyBtn) {
        weekdayApplyBtn.addEventListener('click', function() {
            const modalHtml = `
                <div id="weekdayModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                    <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%;">
                        <div style="padding: 20px; border-bottom: 1px solid #dee2e6; background: #198754; color: white; border-radius: 12px 12px 0 0;">
                            <h5 style="margin: 0;">Apply Timings to Weekdays</h5>
                        </div>
                        <div style="padding: 20px;">
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Opening Time</label>
                                <input type="time" id="bulkOpenTime" class="form-control" value="09:00" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 6px;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Closing Time</label>
                                <input type="time" id="bulkCloseTime" class="form-control" value="22:00" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 6px;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px;">Break Start (Optional)</label>
                                <input type="time" id="bulkBreakStart" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 6px;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px;">Break End (Optional)</label>
                                <input type="time" id="bulkBreakEnd" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 6px;">
                            </div>
                            <div class="form-check">
                                <input type="checkbox" id="applyToWeekend" class="form-check-input" style="margin-right: 8px;">
                                <label class="form-check-label">Apply to weekends (Sat & Sun) as well</label>
                            </div>
                        </div>
                        <div style="padding: 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px;">
                            <button id="closeWeekdayModal" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                            <button id="applyWeekdayBtn" style="padding: 8px 20px; background: #198754; color: white; border: none; border-radius: 6px; cursor: pointer;">Apply</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            const modal = document.getElementById('weekdayModal');
            const closeBtn = document.getElementById('closeWeekdayModal');
            const applyBtn = document.getElementById('applyWeekdayBtn');
            
            closeBtn.addEventListener('click', () => modal.remove());
            modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
            
            applyBtn.addEventListener('click', async () => {
                const openTime = document.getElementById('bulkOpenTime').value;
                const closeTime = document.getElementById('bulkCloseTime').value;
                const breakStart = document.getElementById('bulkBreakStart').value;
                const breakEnd = document.getElementById('bulkBreakEnd').value;
                const applyToWeekend = document.getElementById('applyToWeekend').checked;
                
                if (!openTime || !closeTime) {
                    alert('Please enter both opening and closing times');
                    return;
                }
                
                if (closeTime <= openTime) {
                    alert('Closing time must be after opening time');
                    return;
                }
                
                applyBtn.disabled = true;
                applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
                
                try {
                    const response = await fetch('{{ route("restaurant.settings.apply-weekday-timings") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            open_time: openTime,
                            close_time: closeTime,
                            break_start: breakStart || null,
                            break_end: breakEnd || null,
                            apply_to_weekend: applyToWeekend
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire('Success', 'Weekday timings applied successfully', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Failed to apply', 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'Network error', 'error');
                } finally {
                    modal.remove();
                }
            });
        });
    }
    
    // ==================== Reset to Default ====================
    const resetBtn = document.getElementById('resetToDefaultBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', async () => {
            const result = await Swal.fire({
                title: 'Reset All Timings?',
                text: 'This will reset all day timings to default (9 AM - 10 PM).',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, reset it!'
            });
            
            if (result.isConfirmed) {
                Swal.fire({ title: 'Resetting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                try {
                    const response = await fetch('{{ route("restaurant.settings.reset-timings") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire('Reset Complete', 'Timings reset to default', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Failed to reset', 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'Network error', 'error');
                }
            }
        });
    }
    
    // ==================== Cancel Button ====================
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', async () => {
            const result = await Swal.fire({
                title: 'Discard changes?',
                text: 'You have unsaved changes that will be lost.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, discard'
            });
            if (result.isConfirmed) location.reload();
        });
    }
    
    // ==================== Form Validation ====================
    const timingForm = document.getElementById('timingForm');
    if (timingForm) {
        timingForm.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMsg = '';
            
            document.querySelectorAll('.status-toggle:checked').forEach(toggle => {
                const row = toggle.closest('tr');
                const day = row.querySelector('td:first strong').textContent;
                const openTime = row.querySelector('.open-time').value;
                const closeTime = row.querySelector('.close-time').value;
                const breakStart = row.querySelector('.break-start').value;
                const breakEnd = row.querySelector('.break-end').value;
                
                if (!openTime || !closeTime) {
                    errorMsg = `${day}: Opening and closing times are required`;
                    isValid = false;
                    return;
                }
                
                if (closeTime <= openTime) {
                    errorMsg = `${day}: Closing time must be after opening time`;
                    isValid = false;
                    return;
                }
                
                if ((breakStart && !breakEnd) || (!breakStart && breakEnd)) {
                    errorMsg = `${day}: Both break times are required together`;
                    isValid = false;
                    return;
                }
                
                if (breakStart && breakEnd && breakEnd <= breakStart) {
                    errorMsg = `${day}: Break end must be after break start`;
                    isValid = false;
                    return;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                Swal.fire({ icon: 'error', title: 'Validation Error', text: errorMsg });
            }
        });
    }
});
</script>
@endpush
@endsection
