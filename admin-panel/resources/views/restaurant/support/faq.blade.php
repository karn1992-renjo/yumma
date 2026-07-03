{{-- resources/views/restaurant/support/faq.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'FAQs')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Frequently Asked Questions</h1>
            <p>Find answers to common questions about using the platform</p>
        </div>
        <a href="{{ route('restaurant.support.index') }}" class="btn btn-outline-primary rounded-3">
            <i class="fas fa-arrow-left me-2"></i> Back to Support
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Search -->
    <div class="col-12">
        <div class="stat-card">
            <div class="header-search-wrapper" style="max-width: 100%;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="faqSearch" class="form-control" 
                       placeholder="Search FAQs..." style="padding: 12px 16px 12px 44px;">
            </div>
        </div>
    </div>
    
    <!-- FAQ Categories -->
    @foreach($categories as $category)
    <div class="col-lg-6">
        <div class="stat-card">
            <h5 class="mb-4 fw-bold">
                <i class="fas fa-folder me-2 text-primary"></i> {{ $category }}
            </h5>
            
            <div class="accordion" id="accordion{{ Str::slug($category) }}">
                @foreach($faqs->where('category', $category) as $index => $faq)
                <div class="accordion-item border-0 mb-2">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-light rounded-3 shadow-none" 
                                type="button" data-bs-toggle="collapse" 
                                data-bs-target="#faq{{ Str::slug($category) }}{{ $index }}">
                            {{ $faq['question'] }}
                        </button>
                    </h2>
                    <div id="faq{{ Str::slug($category) }}{{ $index }}" 
                         class="accordion-collapse collapse" 
                         data-bs-parent="#accordion{{ Str::slug($category) }}">
                        <div class="accordion-body text-muted">
                            {{ $faq['answer'] }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach
    
    <!-- Still Need Help -->
    <div class="col-12">
        <div class="stat-card text-center py-4">
            <div class="mb-3">
                <i class="fas fa-headset fa-4x text-primary opacity-50"></i>
            </div>
            <h4 class="mb-2">Still Need Help?</h4>
            <p class="text-muted mb-4">Can't find what you're looking for? Our support team is here to help.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="{{ route('restaurant.support.create') }}" class="btn btn-primary rounded-3">
                    <i class="fas fa-ticket-alt me-2"></i> Create Ticket
                </a>
                <a href="{{ route('restaurant.support.contact') }}" class="btn btn-outline-primary rounded-3">
                    <i class="fas fa-comments me-2"></i> Live Chat
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('faqSearch').addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.accordion-item').forEach(function(item) {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    });
</script>
@endsection
