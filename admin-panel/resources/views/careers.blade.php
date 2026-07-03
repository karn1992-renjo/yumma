@php
    $appName = App\Models\AppSetting::getValue('app_name', 'FoodFlow');
    $contactEmail = App\Models\AppSetting::getValue('contact_email', 'careers@foodflow.com');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers | {{ $appName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@include('partials.public-blade-polish')
</head>
<body class="bg-light">
    <main class="container py-5">
        <a href="{{ route('home') }}" class="text-decoration-none mb-4 d-inline-block">&larr; Back to home</a>
        <div class="bg-white shadow-sm rounded-4 p-5">
            <h1 class="display-6 fw-bold">Careers at {{ $appName }}</h1>
            <p class="lead text-muted">Join our growing team and help shape the future of food delivery.</p>

            <div class="row mt-5 g-4">
                <div class="col-md-4">
                    <div class="border rounded-4 p-4 h-100">
                        <h2 class="h5 fw-bold">Customer Experience</h2>
                        <p class="mb-0">Build delightful journeys for customers and partners.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-4 h-100">
                        <h2 class="h5 fw-bold">Operations</h2>
                        <p class="mb-0">Help us grow safely, efficiently, and with local market care.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-4 p-4 h-100">
                        <h2 class="h5 fw-bold">Engineering</h2>
                        <p class="mb-0">Build the systems behind fast order placement and delivery tracking.</p>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <h2 class="h5 fw-bold">Apply now</h2>
                <p class="text-muted">Send your resume and a short note to <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>. We are looking for passionate individuals who want to grow with us.</p>
            </div>
        </div>
    </main>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
