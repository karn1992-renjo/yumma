{{-- resources/views/restaurant/settings/partials/timing-modals.blade.php --}}

<!-- Copy Timings Modal -->
<div class="modal fade" id="copyTimingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-copy me-2"></i>Copy Timings
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Copy timings from <strong id="sourceDayLabel"></strong> to:</p>
                <div class="row" id="copyDaysList">
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input copy-day-checkbox" value="monday" id="copy_monday">
                            <label class="form-check-label" for="copy_monday">Monday</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input copy-day-checkbox" value="tuesday" id="copy_tuesday">
                            <label class="form-check-label" for="copy_tuesday">Tuesday</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input copy-day-checkbox" value="wednesday" id="copy_wednesday">
                            <label class="form-check-label" for="copy_wednesday">Wednesday</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input copy-day-checkbox" value="thursday" id="copy_thursday">
                            <label class="form-check-label" for="copy_thursday">Thursday</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input copy-day-checkbox" value="friday" id="copy_friday">
                            <label class="form-check-label" for="copy_friday">Friday</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input copy-day-checkbox" value="saturday" id="copy_saturday">
                            <label class="form-check-label" for="copy_saturday">Saturday</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input copy-day-checkbox" value="sunday" id="copy_sunday">
                            <label class="form-check-label" for="copy_sunday">Sunday</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmCopy">
                    <i class="fas fa-check me-1"></i>Copy Timings
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Apply Timings Modal -->
<div class="modal fade" id="weekdayApplyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-clock me-2"></i>Apply Timings to Weekdays
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Opening Time</label>
                    <input type="time" id="bulkOpenTime" class="form-control" value="09:00">
                    <small class="text-muted">Set the opening time for weekdays</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Closing Time</label>
                    <input type="time" id="bulkCloseTime" class="form-control" value="22:00">
                    <small class="text-muted">Set the closing time for weekdays</small>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Break Start (Optional)</label>
                        <input type="time" id="bulkBreakStart" class="form-control" placeholder="--:--">
                        <small class="text-muted">e.g., 14:00 for lunch break</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Break End (Optional)</label>
                        <input type="time" id="bulkBreakEnd" class="form-control" placeholder="--:--">
                        <small class="text-muted">e.g., 15:00 for break end</small>
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="applyToWeekend" value="1">
                    <label class="form-check-label" for="applyToWeekend">
                        Apply to weekends (Saturday & Sunday) as well
                    </label>
                </div>
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-1"></i>
                    This will apply the same timings to all weekdays (Monday-Friday).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="applyWeekdayTimingsBtn">
                    <i class="fas fa-check me-1"></i>Apply to Weekdays
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Go Offline Modal -->
<div class="modal fade" id="goOfflineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('restaurant.settings.go-offline') }}" method="POST" id="goOfflineForm">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-power-off me-2"></i>Go Offline
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Going offline will hide your restaurant from customers and prevent new orders.
                    </div>
                    
                    <p class="mb-3">Please select a reason for going offline:</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason</label>
                        <select name="reason" id="offlineReasonSelect" class="form-select" required>
                            <option value="">Select a reason...</option>
                            <option value="Technical Issue">🛠️ Technical Issue</option>
                            <option value="Low Stock">📦 Low Stock / Out of Stock</option>
                            <option value="Staff Shortage">👥 Staff Shortage</option>
                            <option value="Maintenance">🔧 Maintenance / Renovation</option>
                            <option value="Holiday">🎉 Holiday / Vacation</option>
                            <option value="Weather Condition">🌧️ Weather Condition</option>
                            <option value="Temporary Break">☕ Temporary Break</option>
                            <option value="High Order Volume">📈 High Order Volume (Temporarily Paused)</option>
                            <option value="Other">📝 Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="otherReasonDiv" style="display: none;">
                        <label class="form-label fw-semibold">Please specify</label>
                        <textarea name="other_reason" class="form-control" rows="2" placeholder="Enter your reason details..."></textarea>
                    </div>
                    
                    <div class="form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="confirmOffline" required>
                        <label class="form-check-label" for="confirmOffline">
                            I confirm that I want to take my restaurant offline
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmOfflineBtn" disabled>
                        <i class="fas fa-power-off me-1"></i>Go Offline
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success/Info Modal (Optional) -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="infoModalTitle">Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="infoModalMessage">
                <!-- Dynamic message -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Copy timings functionality
    let sourceDay = null;
    
    $('.copy-day-btn').click(function() {
        sourceDay = $(this).data('day');
        const sourceDayName = $(this).closest('tr').find('td:first strong').text();
        $('#sourceDayLabel').text(sourceDayName);
        
        // Uncheck all checkboxes first
        $('.copy-day-checkbox').prop('checked', false);
        
        $('#copyTimingsModal').modal('show');
    });
    
    $('#confirmCopy').click(function() {
        const selectedDays = [];
        $('.copy-day-checkbox:checked').each(function() {
            selectedDays.push($(this).val());
        });
        
        if (selectedDays.length === 0) {
            showInfoModal('Warning', 'Please select at least one day to copy to');
            return;
        }
        
        // Show loading state
        const btn = $(this);
        const originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Copying...').prop('disabled', true);
        
        $.ajax({
            url: '{{ route("restaurant.settings.copy-timings") }}',
            method: 'POST',
            data: {
                from_day: sourceDay,
                to_days: selectedDays,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    showInfoModal('Success', 'Timings copied successfully!', true);
                } else {
                    showInfoModal('Error', response.message || 'Failed to copy timings');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Failed to copy timings';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showInfoModal('Error', errorMsg);
            },
            complete: function() {
                btn.html(originalText).prop('disabled', false);
                $('#copyTimingsModal').modal('hide');
            }
        });
    });
    
    // Apply weekday timings
    $('#applyWeekdayTimingsBtn').click(function() {
        const openTime = $('#bulkOpenTime').val();
        const closeTime = $('#bulkCloseTime').val();
        const breakStart = $('#bulkBreakStart').val();
        const breakEnd = $('#bulkBreakEnd').val();
        const applyToWeekend = $('#applyToWeekend').is(':checked');
        
        if (!openTime || !closeTime) {
            showInfoModal('Validation Error', 'Please enter both opening and closing times');
            return;
        }
        
        if (closeTime <= openTime) {
            showInfoModal('Validation Error', 'Closing time must be after opening time');
            return;
        }
        
        if (breakStart && !breakEnd) {
            showInfoModal('Validation Error', 'Break end time is required when break start is set');
            return;
        }
        
        if (!breakStart && breakEnd) {
            showInfoModal('Validation Error', 'Break start time is required when break end is set');
            return;
        }
        
        if (breakStart && breakEnd && breakEnd <= breakStart) {
            showInfoModal('Validation Error', 'Break end time must be after break start time');
            return;
        }
        
        // Show loading state
        const btn = $(this);
        const originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Applying...').prop('disabled', true);
        
        $.ajax({
            url: '{{ route("restaurant.settings.apply-weekday-timings") }}',
            method: 'POST',
            data: {
                open_time: openTime,
                close_time: closeTime,
                break_start: breakStart || null,
                break_end: breakEnd || null,
                apply_to_weekend: applyToWeekend ? 1 : 0,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    showInfoModal('Success', 'Weekday timings applied successfully!', true);
                } else {
                    showInfoModal('Error', response.message || 'Failed to apply timings');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Failed to apply timings';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showInfoModal('Error', errorMsg);
            },
            complete: function() {
                btn.html(originalText).prop('disabled', false);
                $('#weekdayApplyModal').modal('hide');
            }
        });
    });
    
    // Reset to default functionality
    $('#resetToDefaultBtn').click(function() {
        Swal.fire({
            title: 'Reset All Timings?',
            text: 'This will reset all day timings to default (9:00 AM - 10:00 PM). Break times will be removed. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, reset it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Resetting...',
                    text: 'Please wait while we reset your timings',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: '{{ route("restaurant.settings.reset-timings") }}',
                    method: 'POST',
                    data: { 
                        _token: '{{ csrf_token() }}' 
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Reset Complete!',
                                text: 'All timings have been reset to default',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to reset timings', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to reset timings. Please try again.', 'error');
                    }
                });
            }
        });
    });
    
    // Go offline form handling
    const reasonSelect = document.getElementById('offlineReasonSelect');
    const otherReasonDiv = document.getElementById('otherReasonDiv');
    const confirmOffline = document.getElementById('confirmOffline');
    const confirmOfflineBtn = document.getElementById('confirmOfflineBtn');
    
    if (reasonSelect) {
        reasonSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                otherReasonDiv.style.display = 'block';
            } else {
                otherReasonDiv.style.display = 'none';
            }
        });
    }
    
    if (confirmOffline) {
        confirmOffline.addEventListener('change', function() {
            confirmOfflineBtn.disabled = !this.checked;
        });
    }
    
    // Helper function to show info modal
    function showInfoModal(title, message, reload = false) {
        $('#infoModalTitle').text(title);
        $('#infoModalMessage').html('<div class="alert alert-' + (title === 'Success' ? 'success' : 'danger') + ' mb-0">' + message + '</div>');
        $('#infoModal').modal('show');
        
        if (reload) {
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    }
});
</script>
@endpush
