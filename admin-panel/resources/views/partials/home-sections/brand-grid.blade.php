<section class="categories-section py-5">
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
                    <h2 class="section-title" style="text-align:left;">{!! $section['title'] !!}</h2>
                    @if($section['subtitle'])
                        <p class="section-subtitle" style="text-align:left;">{{ $section['subtitle'] }}</p>
                    @endif
                </div>
                <div class="row g-4">
                    @foreach($section['items'] as $brand)
                        <div class="col-md-2 col-4 text-center">
                            <a href="/restaurants/{{ $brand['restaurant_id'] ?? $brand['id'] }}" class="text-decoration-none">
                                <div class="mx-auto rounded-circle border bg-white overflow-hidden d-flex align-items-center justify-content-center shadow-sm" style="width:88px;height:88px;">
                                    @if(!empty($brand['image']) || !empty($brand['logo']) || !empty($brand['logo_image']))
                                        <img src="{{ $brand['image'] ?? $brand['logo'] ?? $brand['logo_image'] }}" alt="{{ $brand['name'] }}" class="w-100 h-100" style="object-fit: cover;">
                                    @else
                                        <span class="fw-bold text-warning">{{ strtoupper(substr($brand['name'], 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div class="mt-2 fw-semibold text-dark small">{{ $brand['name'] }}</div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
