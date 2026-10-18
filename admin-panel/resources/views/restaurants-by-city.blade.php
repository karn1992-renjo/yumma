{{-- resources/views/restaurants-by-city.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
        $cityDisplayName = $city['name'];
        $cityTitle = "Restaurants in {$cityDisplayName} - Order Online";
        $cuisineList = $topCuisines->implode(', ');
        $cityDescription = $cuisineList !== ''
            ? "Order online from {$city['count']} restaurants in {$cityDisplayName} including {$cuisineList}. Fast delivery, live order tracking, and easy payments."
            : "Order online from {$city['count']} restaurants in {$cityDisplayName}. Fast delivery, live order tracking, and easy payments.";
        $cityCanonical = route('city.restaurants', $city['slug']);
    @endphp
    <title>{{ $cityTitle }} | {{ $appName ?? config('app.name') }}</title>
    @include('partials.seo-meta', [
        'metaTitle' => $cityTitle,
        'metaDescription' => $cityDescription,
        'canonicalUrl' => $cityCanonical,
    ])

    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => "Restaurants in {$cityDisplayName}", 'item' => $cityCanonical],
        ],
    ], JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => "Restaurants in {$cityDisplayName}",
        'itemListElement' => $restaurants->values()->map(function ($r, $i) {
            return [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => route('restaurant.show', $r['slug'] ?? $r['id']),
                'name' => $r['name'],
            ];
        })->all(),
    ], JSON_UNESCAPED_SLASHES) !!}
    </script>
    @if($restaurants->isNotEmpty())
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_filter([
            [
                '@type' => 'Question',
                'name' => "How many restaurants deliver in {$cityDisplayName}?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => "There are {$city['count']} restaurants currently taking orders in {$cityDisplayName}.",
                ],
            ],
            $avgDeliveryTime ? [
                '@type' => 'Question',
                'name' => "How fast is food delivery in {$cityDisplayName}?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => "Average delivery time for restaurants in {$cityDisplayName} is around {$avgDeliveryTime} minutes, depending on the restaurant and your exact location.",
                ],
            ] : null,
        ]),
    ], JSON_UNESCAPED_SLASHES) !!}
    </script>
    @endif

    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>
    @include('partials.public-blade-polish')
    <style>
        :root {
            --app-success: #22C55E;
            --app-danger: #E53935;
            --app-card-radius: 24px;
        }
        body { font-family: 'Nunito Sans', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Restaurant card -- matches home_new.blade.php's .restaurant-card component */
        .restaurant-card {
            background: white;
            border-radius: var(--app-card-radius, 24px);
            border: 1px solid #F1F1F1;
            overflow: hidden;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.25s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            margin-bottom: 0;
            position: relative;
        }
        .restaurant-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
        }
        .restaurant-img {
            position: relative;
            height: 180px;
            overflow: hidden;
        }
        .restaurant-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s;
        }
        .restaurant-card:hover .restaurant-img img {
            transform: scale(1.05);
        }
        .restaurant-logo-overlay {
            position: absolute;
            left: 14px;
            bottom: -28px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: white;
            border: 3px solid white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            z-index: 2;
        }
        .restaurant-logo-overlay img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .restaurant-info {
            padding: 38px 16px 16px;
        }
        .restaurant-name {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 17px;
            font-weight: 900;
            color: #1C1C1C;
            margin-bottom: 4px;
        }
        .restaurant-cuisine {
            font-size: 13px;
            font-weight: 500;
            color: #686B78;
            margin-bottom: 8px;
        }
        .restaurant-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .rating {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--app-success);
            font-size: 13px;
            font-weight: 700;
        }
        .delivery-time {
            font-size: 13px;
            font-weight: 500;
            color: #686B78;
        }
        .restaurant-closed-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--app-danger);
        }
    </style>
</head>
<body>

<!-- Unified Frontend Header -->
@include('partials.frontend-header')

<main class="container py-5">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Restaurants in {{ $cityDisplayName }}</li>
        </ol>
    </nav>

    <h1 class="fw-bold mb-2">Restaurants in {{ $cityDisplayName }}</h1>
    <p class="text-muted mb-4" style="max-width: 760px;">
        {{ $cityDescription }}
    </p>

    @if($restaurants->isEmpty())
        <div class="text-center py-5">
            <p class="fs-5 text-muted">No restaurants are currently taking orders in {{ $cityDisplayName }}.</p>
            <a href="{{ route('home') }}" class="btn btn-primary mt-2">Browse all restaurants</a>
        </div>
    @else
        <div class="row g-4">
            @foreach($restaurants as $restaurant)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('restaurant.show', $restaurant['slug'] ?? $restaurant['id']) }}" class="restaurant-card">
                        <div class="restaurant-img">
                            <img src="{{ $restaurant['image'] ?: 'https://placehold.co/400x300/E8E8E8/9C9C9C?text=No+Image' }}" alt="{{ $restaurant['name'] }}" width="400" height="300" loading="lazy">
                            @if(!empty($restaurant['logo']))
                                <div class="restaurant-logo-overlay"><img src="{{ $restaurant['logo'] }}" alt=""></div>
                            @endif
                        </div>
                        <div class="restaurant-info">
                            <div class="restaurant-name">{{ $restaurant['name'] }}</div>
                            <div class="restaurant-cuisine">{{ $restaurant['cuisine'] }}</div>
                            <div class="restaurant-meta">
                                @if(($restaurant['rating'] ?? 0) > 0)
                                    <div class="rating"><i class="fas fa-star me-1"></i>{{ number_format((float) $restaurant['rating'], 1) }}</div>
                                @endif
                                @if(!$restaurant['is_open'])
                                    <div class="restaurant-closed-text">Closed</div>
                                @else
                                    <div class="delivery-time">
                                        <i class="far fa-clock me-1"></i>{{ $restaurant['delivery_time'] }}-{{ $restaurant['delivery_time'] + 8 }} mins
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @if($restaurants->isNotEmpty())
    <div class="mt-5">
        <h2 class="h5 fw-bold mb-3">Frequently Asked Questions</h2>
        <div class="accordion" id="cityFaq">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqCount">
                        How many restaurants deliver in {{ $cityDisplayName }}?
                    </button>
                </h3>
                <div id="faqCount" class="accordion-collapse collapse show" data-bs-parent="#cityFaq">
                    <div class="accordion-body">There are {{ $city['count'] }} restaurants currently taking orders in {{ $cityDisplayName }}.</div>
                </div>
            </div>
            @if($avgDeliveryTime)
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqSpeed">
                        How fast is food delivery in {{ $cityDisplayName }}?
                    </button>
                </h3>
                <div id="faqSpeed" class="accordion-collapse collapse" data-bs-parent="#cityFaq">
                    <div class="accordion-body">Average delivery time for restaurants in {{ $cityDisplayName }} is around {{ $avgDeliveryTime }} minutes, depending on the restaurant and your exact location.</div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    @if($otherCities->isNotEmpty())
    <div class="mt-5">
        <h2 class="h6 fw-bold text-muted mb-3">Other cities we deliver to</h2>
        <div class="d-flex flex-wrap gap-2">
            @foreach($otherCities as $other)
                <a href="{{ route('city.restaurants', $other['slug']) }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                    Restaurants in {{ $other['name'] }}
                </a>
            @endforeach
        </div>
    </div>
    @endif
</main>

<!-- Unified Frontend Footer -->
@include('partials.frontend-footer')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
