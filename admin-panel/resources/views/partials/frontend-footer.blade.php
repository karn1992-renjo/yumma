@php
    $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'Swado'));
    $appLogo = App\Models\AppSetting::getValue('app_logo', null);
    $footerDescription = App\Models\AppSetting::getValue('footer_description', 'Order food & groceries. Discover best restaurants. ' . $appName . ' it!');
    $partnerNavText = App\Models\AppSetting::getValue('partner_nav_text', 'Partner with Us');

    // Real App Download Links from AppDownloadService
    $appDownloadService = app(\App\Services\AppDownloadService::class);
    $customerPlayStoreUrl = $appDownloadService->androidUrl('customer') ?: route('app.download');
    $customerAppStoreUrl = $appDownloadService->iosUrl('customer') ?: route('app.download');

    // Real Delivery Zones in DB
    $realDeliveryZones = [];
    try {
        $areas = App\Models\DeliveryArea::where('is_active', true)->get();
        foreach ($areas as $area) {
            $realDeliveryZones[] = $area->name;
        }
        if (empty($realDeliveryZones)) {
            $branchZones = App\Models\BranchZone::where('is_active', true)->pluck('name')->toArray();
            if (!empty($branchZones)) {
                $realDeliveryZones = $branchZones;
            }
        }
        if (empty($realDeliveryZones)) {
            $resCities = App\Models\Restaurant::where('is_verified', true)
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->pluck('city')
                ->map(fn($c) => $c . ' Delivery Zone')
                ->toArray();
            if (!empty($resCities)) {
                $realDeliveryZones = $resCities;
            }
        }
        if (empty($realDeliveryZones)) {
            $bizCity = App\Models\AppSetting::getValue('business_city');
            if ($bizCity) $realDeliveryZones = [$bizCity . ' Delivery Zone'];
        }
    } catch (\Throwable $e) {}

    // Real, crawlable "restaurants in {city}" SEO links -- its own column,
    // always showing every city we serve as a real "/restaurants-in-X" link.
    //
    // Delivery Zones (above) is then filtered to drop any entry that's just
    // the same place Popular Cities already links to -- e.g. "Adityapur"
    // wouldn't also appear there as a second, JS-only, non-SEO entry for
    // the same city. A zone keeps its plain JS drawer behaviour whenever it
    // *doesn't* match a real place we have a city page for (e.g. an
    // admin-named "Zone A").
    $popularCities = [];
    try {
        $popularCities = app(\App\Services\CityDirectoryService::class)
            ->listActiveCities()
            ->sortByDesc('count')
            ->take(8)
            ->all();
    } catch (\Throwable $e) {}

    $popularCitySlugs = collect($popularCities)->pluck('slug')->all();
    $realDeliveryZones = array_values(array_filter($realDeliveryZones, function ($zoneName) use ($popularCitySlugs) {
        $bareName = trim(preg_replace('/\s+Delivery Zone$/i', '', $zoneName));
        return ! in_array(\Illuminate\Support\Str::slug($bareName), $popularCitySlugs, true);
    }));

    // Real Social Links from AppSetting
    $socialFacebook = App\Models\AppSetting::getValue('social_facebook', App\Models\AppSetting::getValue('facebook_url', ''));
    $socialInstagram = App\Models\AppSetting::getValue('social_instagram', App\Models\AppSetting::getValue('instagram_url', ''));
    $socialTwitter = App\Models\AppSetting::getValue('social_twitter', App\Models\AppSetting::getValue('twitter_url', ''));
    $socialLinkedin = App\Models\AppSetting::getValue('social_linkedin', App\Models\AppSetting::getValue('linkedin_url', ''));
@endphp

<style>
    .swiggy-footer {
        background: #02060c;
        color: #808080;
        padding: 64px 0 32px;
        font-family: inherit;
        border-top: 1px solid #1a1e24;
    }

    .swiggy-footer-app-banner {
        background: var(--app-bg, #f0f0f5);
        border: 1px solid var(--app-border, transparent);
        border-radius: var(--app-card-radius, 24px);
        padding: 24px 36px;
        margin-bottom: 50px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }

    .swiggy-footer-app-title {
        font-size: 22px;
        font-weight: 800;
        color: #02060c;
        margin: 0;
        letter-spacing: -0.4px;
    }

    .swiggy-footer-app-badges {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .swiggy-footer-app-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: #000000;
        color: #ffffff !important;
        padding: 10px 20px;
        border-radius: 12px;
        text-decoration: none;
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .swiggy-footer-app-btn:hover {
        transform: translateY(-2px);
        opacity: 0.9;
    }

    .swiggy-footer-app-btn i {
        font-size: 22px;
    }

    .swiggy-footer-app-btn-text {
        display: flex;
        flex-direction: column;
        line-height: 1.15;
    }

    .swiggy-footer-app-btn-sub {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #a0a0a0;
    }

    .swiggy-footer-app-btn-main {
        font-size: 14px;
        font-weight: 700;
    }

    /* Columns */
    .swiggy-footer-brand {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .swiggy-footer-logo-title {
        font-size: 26px;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -0.5px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .swiggy-footer-copy {
        font-size: 14px;
        color: #686b78;
        line-height: 1.6;
    }

    .swiggy-footer-col-title {
        font-size: 15px;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 20px;
        letter-spacing: -0.2px;
    }

    .swiggy-footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .swiggy-footer-links a {
        color: #93959f;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.15s ease, transform 0.15s ease;
        display: inline-block;
    }

    .swiggy-footer-links a:hover {
        color: #ffffff;
        transform: translateX(3px);
    }

    /* Socials */
    .swiggy-footer-socials {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-top: 18px;
    }

    .swiggy-footer-social-link {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #1c212a;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .swiggy-footer-social-link:hover {
        background: var(--primary);
        color: #ffffff;
        transform: translateY(-2px);
    }

    .swiggy-footer-bottom {
        margin-top: 50px;
        padding-top: 24px;
        border-top: 1px solid #1c212a;
        font-size: 13px;
        color: #686b78;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
</style>

<footer class="swiggy-footer">
    <div class="container">
        <!-- App Download Promo Banner with Real Links -->
        <div class="swiggy-footer-app-banner">
            <div>
                <h3 class="swiggy-footer-app-title">For better experience, download the {{ $appName }} app now</h3>
            </div>
            <div class="swiggy-footer-app-badges">
                <a href="{{ $customerPlayStoreUrl }}" target="_blank" rel="noopener" class="swiggy-footer-app-btn">
                    <i class="fab fa-google-play"></i>
                    <div class="swiggy-footer-app-btn-text">
                        <span class="swiggy-footer-app-btn-sub">Get it on</span>
                        <span class="swiggy-footer-app-btn-main">Google Play</span>
                    </div>
                </a>
                <a href="{{ $customerAppStoreUrl }}" target="_blank" rel="noopener" class="swiggy-footer-app-btn">
                    <i class="fab fa-apple"></i>
                    <div class="swiggy-footer-app-btn-text">
                        <span class="swiggy-footer-app-btn-sub">Download on</span>
                        <span class="swiggy-footer-app-btn-main">App Store</span>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4 justify-content-between">
            <!-- Brand Column -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="swiggy-footer-brand">
                    <a href="{{ route('home') }}" class="swiggy-footer-logo-title">
                        @if($appLogo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}" style="height: 38px; width: auto; object-fit: contain;">
                        @else
                            <span style="color: var(--primary);">{{ $appName }}</span>
                        @endif
                    </a>
                    <p class="swiggy-footer-copy mb-0">
                        © {{ date('Y') }} {{ $appName }} Limited.<br>
                        All rights reserved.
                    </p>
                    <div class="swiggy-footer-socials">
                        @if($socialLinkedin)
                            <a href="{{ $socialLinkedin }}" target="_blank" rel="noopener" class="swiggy-footer-social-link" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        @endif
                        @if($socialInstagram)
                            <a href="{{ $socialInstagram }}" target="_blank" rel="noopener" class="swiggy-footer-social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                        @endif
                        @if($socialFacebook)
                            <a href="{{ $socialFacebook }}" target="_blank" rel="noopener" class="swiggy-footer-social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        @endif
                        @if($socialTwitter)
                            <a href="{{ $socialTwitter }}" target="_blank" rel="noopener" class="swiggy-footer-social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Company Column -->
            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h5 class="swiggy-footer-col-title">Company</h5>
                <ul class="swiggy-footer-links">
                    <li><a href="{{ Route::has('about') ? route('about') : url('/about') }}">About Us</a></li>
                    <li><a href="/partner/register">{{ $partnerNavText }}</a></li>
                    <li><a href="{{ Route::has('careers') ? route('careers') : url('/careers') }}">Careers</a></li>
                    <li><a href="{{ Route::has('blog') ? route('blog') : url('/blog') }}">Team & Culture</a></li>
                    <li><a href="{{ route('home') }}">{{ $appName }} One</a></li>
                    <li><a href="{{ route('home') }}">{{ $appName }} Instamart</a></li>
                    <li><a href="{{ route('home') }}">{{ $appName }} Dineout</a></li>
                </ul>
            </div>

            <!-- Contact Us Column -->
            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h5 class="swiggy-footer-col-title">Contact us</h5>
                <ul class="swiggy-footer-links">
                    <li><a href="{{ Route::has('help') ? route('help') : url('/help') }}">Help & Support</a></li>
                    <li><a href="/partner/register">Partner with us</a></li>
                    <li><a href="/partner/register?type=driver">Ride with us</a></li>
                    <li><a href="{{ Route::has('contact') ? route('contact') : url('/contact') }}">Customer Care</a></li>
                    <li><a href="{{ Route::has('faqs') ? route('faqs') : url('/faqs') }}">FAQs</a></li>
                </ul>
            </div>

            <!-- Delivery Zones Column (Replaces Cities) -->
            <div class="col-lg-2 col-md-4 col-6 mb-4">
                <h5 class="swiggy-footer-col-title">Delivery Zones:</h5>
                <ul class="swiggy-footer-links">
                    @foreach(array_slice($realDeliveryZones, 0, 8) as $zoneName)
                        <li><a href="javascript:void(0)" onclick="selectDrawerDeliveryZone('{{ e($zoneName) }}')">{{ $zoneName }}</a></li>
                    @endforeach
                    <li><a href="javascript:void(0)" onclick="openSwiggyLocationDrawer()" class="fw-bold" style="color: var(--primary);">View all zones <i class="fas fa-chevron-right ms-1" style="font-size:10px;"></i></a></li>
                </ul>
            </div>

            @if(!empty($popularCities))
            <!-- Popular Cities Column (real SEO links, not JS drawer triggers; deduped against Delivery Zones above) -->
            <div class="col-lg-2 col-md-4 col-6 mb-4">
                <h5 class="swiggy-footer-col-title">Popular Cities</h5>
                <ul class="swiggy-footer-links">
                    @foreach($popularCities as $popularCity)
                        <li><a href="{{ route('city.restaurants', $popularCity['slug']) }}">Restaurants in {{ $popularCity['name'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Legal Column -->
            <div class="col-lg-2 col-md-4 col-6 mb-4">
                <h5 class="swiggy-footer-col-title">Legal</h5>
                <ul class="swiggy-footer-links">
                    <li><a href="{{ route('legal.terms') }}">Terms & Conditions</a></li>
                    <li><a href="{{ route('legal.index') }}#cookie-policy">Cookie Policy</a></li>
                    <li><a href="{{ route('legal.privacy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('legal.index') }}#investor">Investor Relations</a></li>
                </ul>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="swiggy-footer-bottom">
            <div>
                Crafted with <i class="fas fa-heart text-danger"></i> for food lovers everywhere.
            </div>
            <div>
                Powered by {{ $appName }} &copy; {{ date('Y') }}.
            </div>
        </div>
    </div>
</footer>