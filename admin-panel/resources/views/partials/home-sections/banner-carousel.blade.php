<section class="collection-section py-5">
    <div class="container">
        @php
            $style = $section['style'] ?? [];
            $backgroundColor = $style['background_color'] ?? '#FFFFFF';
            $backgroundImage = $style['background_image'] ?? null;
            $backgroundOpacity = max(0, min(1, (float) ($style['background_opacity'] ?? 0.88)));
        @endphp
        @if($section['title'] || $section['subtitle'])
            <div class="section-header text-start mb-4">
                @if($section['title'])
                    <h2 class="section-title" style="text-align: left;">{!! $section['title'] !!}</h2>
                @endif
                @if($section['subtitle'])
                    <p class="section-subtitle" style="text-align: left;">{{ $section['subtitle'] }}</p>
                @endif
            </div>
        @endif

        <div id="bannerCarousel{{ $loop->index }}" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner rounded-4 overflow-hidden">
                @foreach($section['items'] as $banner)
                    <div class="carousel-item @if($loop->first) active @endif">
                        @php
                            $bannerImage = $banner->image ? asset('storage/'.$banner->image) : null;
                            $layoutMode = $banner->layout_mode ?? 'text_image';
                            $imageRatio = max(35, min(70, (int) ($banner->image_ratio ?? 46)));
                            $textRatio = 100 - $imageRatio;
                        @endphp
                        <a href="{{ $banner->link ?: '#' }}" class="d-block text-decoration-none">
                            <div class="rounded-4 overflow-hidden" style="height: 360px; background: {{ $backgroundColor }}; position: relative;">
                                @if($backgroundImage)
                                    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image:url('{{ $backgroundImage }}'); background-size:cover; background-position:center; opacity: {{ $backgroundOpacity }};"></div>
                                @endif
                                @if($layoutMode === 'full_image')
                                    @if($bannerImage)
                                        <img src="{{ $bannerImage }}" alt="{{ $banner->title }}" class="w-100 h-100 position-relative" style="object-fit: cover;">
                                    @endif
                                    <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-end" style="background: linear-gradient(to top, rgba(17,24,39,0.72), rgba(17,24,39,0.02));">
                                        <div class="p-4 text-white">
                                            <h3 class="fw-bold mb-2">{{ $banner->title }}</h3>
                                            @if($banner->description)
                                                <p class="mb-0">{{ $banner->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex h-100 position-relative">
                                        <div class="d-flex flex-column justify-content-center px-4 py-4" style="width: {{ $textRatio }}%;">
                                            <div class="small fw-semibold text-uppercase mb-2" style="color:#111827;">Hot Deal</div>
                                            <div class="fw-bold lh-sm mb-2" style="font-size: 2.35rem; color:#FF6B00;">{{ $banner->title }}</div>
                                            @if($banner->description)
                                                <div class="fw-semibold text-dark mb-3">{{ $banner->description }}</div>
                                            @endif
                                            <span class="btn btn-lg text-white fw-bold px-4" style="width: fit-content; border-radius: 14px; background: linear-gradient(135deg, #FF6B00, #FF7A00);">Order Now</span>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center p-4" style="width: {{ $imageRatio }}%;">
                                            @if($bannerImage)
                                                <img src="{{ $bannerImage }}" alt="{{ $banner->title }}" class="w-100 h-100" style="object-fit: contain;">
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
            @if(count($section['items']) > 1)
                <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel{{ $loop->index }}" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel{{ $loop->index }}" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
            @endif
        </div>
    </div>
</section>
