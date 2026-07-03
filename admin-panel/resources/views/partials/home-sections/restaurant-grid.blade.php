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
                            <a href="/restaurants/{{ $restaurant['id'] }}" class="text-decoration-none">
                                <div class="d-flex align-items-center rounded-5 bg-white shadow-sm border overflow-hidden p-3 h-100">
                                    <div class="rounded-4 overflow-hidden flex-shrink-0" style="width: 120px; height: 110px;">
                                        <img src="{{ $restaurant['image'] ?: 'https://placehold.co/400x300/E8E8E8/9C9C9C?text=No+Image' }}" alt="{{ $restaurant['name'] }}" class="w-100 h-100" style="object-fit: cover;">
                                    </div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="d-flex align-items-start justify-content-between gap-2">
                                            <div class="fw-bold text-dark" style="font-size: 1.65rem; line-height:1.05;">{{ $restaurant['name'] }}</div>
                                            @if(($restaurant['rating'] ?? 0) > 0)
                                                <div class="text-success fw-bold"><i class="fas fa-star me-1"></i>{{ number_format((float) $restaurant['rating'], 1) }}</div>
                                            @endif
                                        </div>
                                        <div class="text-muted fw-semibold mt-2">
                                            {{ $restaurant['delivery_time'] }}-{{ $restaurant['delivery_time'] + 8 }} mins
                                            @if($amountForOne > 0)
                                                | Rs. {{ number_format($amountForOne, 2) }} for one
                                            @endif
                                        </div>
                                        <div class="text-secondary mt-2">{{ $restaurant['cuisine'] }}</div>
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
