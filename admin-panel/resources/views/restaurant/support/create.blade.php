{{-- resources/views/restaurant/support/create.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Create Support Ticket')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Create Support Ticket</h1>
            <p>Submit a new support request and we'll help you resolve it</p>
        </div>
        <a href="{{ route('restaurant.support.index') }}" class="btn btn-outline-primary rounded-3">
            <i class="fas fa-arrow-left me-2"></i> Back to Tickets
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="stat-card">
            <form action="{{ route('restaurant.support.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" 
                           class="form-control @error('subject') is-invalid @enderror" 
                           value="{{ old('subject') }}" 
                           placeholder="Brief description of your issue" required>
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                            <option value="">Select Category</option>
                            <option value="order_issue" {{ old('category') == 'order_issue' ? 'selected' : '' }}>Order Issue</option>
                            <option value="payment_issue" {{ old('category') == 'payment_issue' ? 'selected' : '' }}>Payment Issue</option>
                            <option value="technical_support" {{ old('category') == 'technical_support' ? 'selected' : '' }}>Technical Support</option>
                            <option value="account_issue" {{ old('category') == 'account_issue' ? 'selected' : '' }}>Account Issue</option>
                            <option value="general_inquiry" {{ old('category') == 'general_inquiry' ? 'selected' : '' }}>General Inquiry</option>
                        </select>
                        @error('category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                        <select name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                            <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }} selected>Medium</option>
                            <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                            <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea name="description" 
                              class="form-control @error('description') is-invalid @enderror" 
                              rows="6" 
                              placeholder="Describe your issue in detail. Include any error messages, steps to reproduce, etc." 
                              required>{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Attachment (Optional)</label>
                    <div class="bg-light rounded-3 p-4 text-center">
                        <i class="fas fa-paperclip fa-2x text-muted mb-2 d-block"></i>
                        <p class="text-muted small mb-2">Attach screenshots or files (Max 5MB)</p>
                        <input type="file" name="attachment" 
                               class="form-control @error('attachment') is-invalid @enderror">
                        @error('attachment')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('restaurant.support.index') }}" class="btn btn-light rounded-3 btn-lg">Cancel</a>
                    <button type="submit" class="btn btn-primary rounded-3 btn-lg">
                        <i class="fas fa-paper-plane me-2"></i> Submit Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
