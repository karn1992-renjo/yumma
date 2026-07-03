@php
    $appName = App\Models\AppSetting::getValue('app_name', 'FoodFlow');
    $siteDescription = App\Models\AppSetting::getValue('site_description', 'Fast food delivery platform connecting customers to restaurants and delivery partners in real time.');
    $contactEmail = App\Models\AppSetting::getValue('contact_email', 'support@foodflow.com');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | {{ $appName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@include('partials.public-blade-polish')
</head>
<body class="bg-light">
    <main class="container py-5">
        <a href="{{ route('home') }}" class="text-decoration-none mb-4 d-inline-block">&larr; Back to home</a>
        <div class="bg-white shadow-sm rounded-4 p-5">
            <h1 class="display-6 fw-bold">About {{ $appName }}</h1>
            <p class="lead text-muted">{{ $siteDescription }}</p>
            <div class="row mt-5">
                <div class="col-md-6">
                    <h2 class="h4 fw-bold">Our mission</h2>
                    <p>We make it easy to order food from quality restaurants, with fast delivery and reliable customer support.</p>
                </div>
                <div class="col-md-6">
                    <h2 class="h4 fw-bold">What we offer</h2>
                    <ul class="list-unstyled">
                        <li class="mb-2">• A curated restaurant marketplace</li>
                        <li class="mb-2">• Real-time order tracking</li>
                        <li class="mb-2">• Reliable partner onboarding and support</li>
                    </ul>
                </div>
            </div>
            <div class="mt-5">
                <h2 class="h5 fw-bold">Get in touch</h2>
                <p class="text-muted">For partnership or media enquiries, email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>
            </div>
        </div>
    </main>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
