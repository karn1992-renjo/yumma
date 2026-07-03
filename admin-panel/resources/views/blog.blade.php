@php
    $appName = App\Models\AppSetting::getValue('app_name', 'FoodFlow');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog | {{ $appName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
@include('partials.public-blade-polish')
</head>
<body class="bg-light">
    <main class="container py-5">
        <a href="{{ route('home') }}" class="text-decoration-none mb-4 d-inline-block">&larr; Back to home</a>
        <div class="bg-white shadow-sm rounded-4 p-5">
            <h1 class="display-6 fw-bold">{{ $appName }} Blog</h1>
            <p class="lead text-muted">Updates, food trends and news from our delivery network.</p>

            <div class="row g-4 mt-4">
                <div class="col-md-6">
                    <article class="border rounded-4 p-4 h-100">
                        <h2 class="h5">How to choose the best restaurant for delivery</h2>
                        <p class="text-muted">Discover tips for selecting the fastest, tastiest option in your neighborhood.</p>
                        <a href="#" class="text-primary">Read more</a>
                    </article>
                </div>
                <div class="col-md-6">
                    <article class="border rounded-4 p-4 h-100">
                        <h2 class="h5">New partner restaurants joining our platform</h2>
                        <p class="text-muted">See the latest restaurants now available for delivery in your city.</p>
                        <a href="#" class="text-primary">Read more</a>
                    </article>
                </div>
                <div class="col-md-6">
                    <article class="border rounded-4 p-4 h-100">
                        <h2 class="h5">What customers love about next-day delivery</h2>
                        <p class="text-muted">Learn how we ensure reliable service and happy customers every day.</p>
                        <a href="#" class="text-primary">Read more</a>
                    </article>
                </div>
                <div class="col-md-6">
                    <article class="border rounded-4 p-4 h-100">
                        <h2 class="h5">Safety standards for our delivery ecosystem</h2>
                        <p class="text-muted">Understand the steps we take to keep drivers, restaurants and customers safe.</p>
                        <a href="#" class="text-primary">Read more</a>
                    </article>
                </div>
            </div>
        </div>
    </main>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
