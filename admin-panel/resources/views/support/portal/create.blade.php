@extends('layouts.app')

@section('title', 'Start Live Chat')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Start {{ $portalLabel }}</h2>
            <p class="text-muted mb-0">{{ $portalDescription }}</p>
        </div>
        <a href="{{ route($routePrefix . '.index') }}" class="btn btn-outline-primary rounded-pill">
            <i class="fas fa-arrow-left me-2"></i> Back to Chats
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route($routePrefix . '.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" class="form-control rounded-3" placeholder="What do you need help with?" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select rounded-3" required>
                            <option value="general_inquiry">General Inquiry</option>
                            <option value="order_issue">Order Issue</option>
                            <option value="payment_issue">Payment Issue</option>
                            <option value="technical_support">Technical Support</option>
                            <option value="account_issue">Account Issue</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Priority</label>
                        <select name="priority" class="form-select rounded-3" required>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Message</label>
                    <textarea name="description" rows="6" class="form-control rounded-3" placeholder="Describe the issue and include any useful context." required>{{ old('description') }}</textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Attachment</label>
                    <input type="file" name="attachment" class="form-control rounded-3">
                </div>
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-paper-plane me-2"></i> Start Live Chat
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
