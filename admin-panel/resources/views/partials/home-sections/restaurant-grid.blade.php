<section class="restaurants-section">
    <div class="container">
        @php
            $style = $section['style'] ?? [];
            $backgroundColor = $style['background_color'] ?? '#FFFFFF';
            $backgroundImage = $style['background_image'] ?? null;
            $backgroundOpacity = max(0, min(1, (float) ($style['background_opacity'] ?? 0.88)));
        @endphp
        <div class="rounded-5 p-4 position-relative overflow-hidden" style="background-color: {{ $backgroundColor }};">
            @if($backgroundImage)
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image:url('{{ $backgroundImage }}'); background-size:cover; background-position:center; opacity: {{ $backgroundOpacity }};"></div>
            @endif
            <div class="position-relative">
                <div class="section-header text-start mb-4">
                    <h2 class="section-title" style="text-align: left;">{!! $section['title'] !!}</h2>
                    @if($section['subtitle'])
                        <p class="section-subtitle" style="text-align: left;">{{ $section['subtitle'] }}</p>
                    @endif
                </div>
                <div class="row g-4">
                    @foreach($section['items'] as $restaurant)
                        @php
                            $amountForOne = (float) ($restaurant['amount_for_one'] ?? 0);
                        @endphp
                        <div class="col-md-6 col-lg-6">
                            <a href="{{ url('/restaurants/'.($restaurant['slug'] ?? $restaurant['id'])) }}" class="restaurant-card">
                                <div class="restaurant-img">
                                    <img src="{{ $restaurant['image'] ?: 'https://placehold.co/400x300/E8E8E8/9C9C9C?text=No+Image' }}" alt="{{ $restaurant['name'] }}" width="400" height="300" loading="lazy">
                                    @if(!empty($restaurant['logo_image']))
                                        <div class="restaurant-logo-overlay"><img src="{{ $restaurant['logo_image'] }}" alt=""></div>
                                    @endif
                                </div>
                                <div class="restaurant-info">
                                    <div class="restaurant-name">{{ $restaurant['name'] }}</div>
                                    <div class="restaurant-cuisine">{{ $restaurant['cuisine'] }}</div>
                                    <div class="restaurant-meta">
                                        @if(($restaurant['rating'] ?? 0) > 0)
                                            <div class="rating"><i class="fas fa-star me-1"></i>{{ number_format((float) $restaurant['rating'], 1) }}</div>
                                        @endif
                                        @if(($restaurant['is_open'] ?? true) === false)
                                            <div class="restaurant-closed-text">Closed</div>
                                        @else
                                            <div class="delivery-time">
                                                <i class="far fa-clock me-1"></i>{{ $restaurant['delivery_time'] }}-{{ $restaurant['delivery_time'] + 8 }} mins
                                                @if($amountForOne > 0)
                                                    &middot; {{ $currencySymbol }}{{ number_format($amountForOne, $currencyDecimals) }} for one
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
