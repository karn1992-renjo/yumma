@php
    $settings = $settings ?? [];
    $siteName = $settings['site_name'] ?? 'FoodFlow';
    $sections = [
        'terms' => ['title' => 'Terms of Service', 'body' => $settings['legal_terms'] ?? 'Use of this platform is subject to account, order, payment, cancellation and support policies.'],
        'privacy' => ['title' => 'Privacy Policy', 'body' => $settings['legal_privacy'] ?? 'We process customer, restaurant, driver, location and order data to operate delivery and support workflows.'],
        'refund' => ['title' => 'Refund Policy', 'body' => $settings['legal_refund'] ?? 'Refund eligibility depends on payment status, restaurant acceptance, delivery progress and support review.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Legal | {{ $siteName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@include('partials.public-blade-polish')
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="mb-4">
            <a href="{{ route('home') }}" class="text-decoration-none">&larr; Back home</a>
            <h1 class="display-6 fw-bold mt-3">Privacy & Legal</h1>
            <p class="text-muted">Current platform policies for customers, restaurants and delivery partners.</p>
        </div>
        <div class="row g-4">
            @foreach($sections as $key => $section)
                @if($type === 'legal' || $type === $key)
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body p-4">
                                <h2 class="h4 fw-bold">{{ $section['title'] }}</h2>
                                <p class="mb-0" style="white-space: pre-line;">{{ $section['body'] }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
        <p class="text-muted small mt-4">Legal contact: {{ $settings['legal_contact_email'] ?? ($settings['contact_email'] ?? 'support@foodflow.com') }}</p>
    </main>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
