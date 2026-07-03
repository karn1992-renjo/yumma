@php
    $appName = App\Models\AppSetting::getValue('app_name', 'FoodFlow');
    $contactEmail = App\Models\AppSetting::getValue('contact_email', 'support@foodflow.com');
    $contactPhone = App\Models\AppSetting::getValue('contact_phone', '+91 9876543210');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | {{ $appName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@include('partials.public-blade-polish')
</head>
<body class="bg-light">
    <main class="container py-5">
        <a href="{{ route('home') }}" class="text-decoration-none mb-4 d-inline-block">&larr; Back to home</a>
        <div class="bg-white shadow-sm rounded-4 p-5">
            <h1 class="display-6 fw-bold">Contact Us</h1>
            <p class="lead text-muted">We're here to help. Reach out for account, order, or delivery support.</p>

            <div class="row gy-4 mt-4">
                <div class="col-md-6">
                    <div class="border rounded-4 p-4 h-100">
                        <h2 class="h5 fw-bold">Email</h2>
                        <p><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-4 p-4 h-100">
                        <h2 class="h5 fw-bold">Phone</h2>
                        <p><a href="tel:{{ $contactPhone }}">{{ $contactPhone }}</a></p>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <a href="{{ route('support.create') }}" class="btn btn-primary">Submit a Support Ticket</a>
                <a href="{{ route('help') }}" class="btn btn-outline-secondary ms-2">Visit Help Center</a>
            </div>
        </div>
    </main>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
