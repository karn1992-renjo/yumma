<section class="categories-section" id="section-{{ $loop->index }}">
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
                <div class="section-header">
                    <h2 class="section-title">{!! $section['title'] !!}</h2>
                    @if($section['subtitle'])
                        <p class="section-subtitle">{{ $section['subtitle'] }}</p>
                    @endif
                </div>
                <div class="row g-4">
                    @foreach($section['items'] as $cuisine)
                        @php
                            $image = $cuisine->image ? asset('storage/'.$cuisine->image) : null;
                        @endphp
                        <div class="col-md-2 col-4">
                            <div class="category-card" onclick="searchByCategory('{{ e($cuisine->name) }}')">
                                <div class="category-icon overflow-hidden">
                                    @if($image)
                                        <img src="{{ $image }}" alt="{{ $cuisine->name }}" class="w-100 h-100" style="object-fit: cover;">
                                    @else
                                        <i class="{{ $cuisine->icon ?: 'fas fa-utensils' }}"></i>
                                    @endif
                                </div>
                                <div class="category-name">{{ $cuisine->name }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
