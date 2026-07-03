@php
    $appName = App\Models\AppSetting::getValue('app_name', 'FoodFlow');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center | {{ $appName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@include('partials.public-blade-polish')
</head>
<body class="bg-light">
    <main class="container py-5">
        <a href="{{ route('home') }}" class="text-decoration-none mb-4 d-inline-block">&larr; Back to home</a>
        <div class="bg-white shadow-sm rounded-4 p-5">
            <h1 class="display-6 fw-bold">Help Center</h1>
            <p class="lead text-muted">Find support articles and get help with orders, account settings, and deliveries.</p>

            <div class="row mt-5 g-4">
                <div class="col-md-4">
                    <div class="border rounded-4 p-4 h-100">
                        <h2 class="h6 fw-bold">Ordering</h2>
                        <p class="mb-0 text-muted">How to place orders, track status and reorder favorites.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-4 h-100">
                        <h2 class="h6 fw-bold">Payments</h2>
                        <p class="mb-0 text-muted">Learn about available payment methods and billing questions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-4 h-100">
                        <h2 class="h6 fw-bold">Account</h2>
                        <p class="mb-0 text-muted">Manage your profile, address book, and notification preferences.</p>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <a href="{{ route('support.create') }}" class="btn btn-primary">Contact Support</a>
                <a href="{{ route('faqs') }}" class="btn btn-outline-secondary ms-2">Browse FAQs</a>
            </div>
        </div>
    </main>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
