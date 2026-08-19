@php
    $style = $section['style'] ?? [];
    $items = collect($section['items'] ?? []);
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();

    $offerType = function (array $offer): string {
        $reward = is_array($offer['rewards'] ?? null)
            ? $offer['rewards']
            : (is_array($offer['reward_config'] ?? null) ? $offer['reward_config'] : []);

        return strtolower(trim((string) (
            $offer['promotion_type']
            ?? $offer['reward_type']
            ?? $offer['discount_type']
            ?? ($reward['type'] ?? '')
        )));
    };

    $isBundle = fn (string $type): bool => str_contains($type, 'combo') || str_contains($type, 'meal');
    $isItemReward = fn (string $type): bool => $type === 'bogo' || str_starts_with($type, 'buy_') || str_starts_with($type, 'free_');
    $menuItems = fn (array $offer) => collect($offer['menu_items'] ?? [])->filter(fn ($item) => is_array($item))->values();

    $rewardText = function (array $offer) use ($offerType, $currencySymbol, $currencyDecimals): string {
        $reward = is_array($offer['rewards'] ?? null)
            ? $offer['rewards']
            : (is_array($offer['reward_config'] ?? null) ? $offer['reward_config'] : []);
        $type = $offerType($offer);
        $value = (float) ($offer['discount_value'] ?? $offer['value'] ?? ($reward['value'] ?? 0));

        if ($type === 'bogo') {
            return 'Buy 1 Get 1';
        }
        if (str_starts_with($type, 'buy_')) {
            $buy = $reward['buy_quantity'] ?? null;
            $free = $reward['free_quantity'] ?? null;
            return $buy && $free ? "Buy {$buy} Get {$free}" : 'Buy More Get More';
        }
        if (str_starts_with($type, 'free_')) {
            return 'Free Item';
        }
        if (str_contains($type, 'combo') || str_contains($type, 'meal')) {
            return $value > 0 ? $currencySymbol.number_format($value, $currencyDecimals).' Deal' : 'Combo Deal';
        }
        if (str_contains($type, 'percentage') || ($offer['discount_type'] ?? null) === 'percentage') {
            return rtrim(rtrim(number_format($value, 1), '0'), '.').'% OFF';
        }

        return $value > 0 ? $currencySymbol.number_format($value, $currencyDecimals).' OFF' : 'Live Offer';
    };

    $imageFor = function (array $offer) {
        foreach (['promo_image_url', 'promo_image', 'image_url', 'image', 'banner_image'] as $key) {
            $value = trim((string) ($offer[$key] ?? ''));
            if ($value !== '' && $value !== 'null') {
                return $value;
            }
        }

        foreach (($offer['menu_items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach (['image_url', 'image', 'thumbnail_url'] as $key) {
                $value = trim((string) ($item[$key] ?? ''));
                if ($value !== '' && $value !== 'null') {
                    return $value;
                }
            }
        }

        return null;
    };

    $displayItems = $items->flatMap(function ($offer) use ($offerType, $isBundle, $isItemReward, $menuItems) {
        if (! is_array($offer)) {
            return [];
        }

        $type = $offerType($offer);
        $menu = $menuItems($offer);
        if (! $isItemReward($type) || $isBundle($type) || $menu->count() <= 1) {
            return [$offer];
        }

        return $menu
            ->reject(fn ($item) => (bool) ($item['is_reward_item'] ?? false))
            ->whenEmpty(fn () => $menu)
            ->map(function ($item) use ($offer) {
                $itemId = $item['menu_item_id'] ?? $item['id'] ?? md5(json_encode($item));

                return array_merge($offer, [
                    'display_id' => ($offer['display_id'] ?? ('promotion:'.($offer['id'] ?? '0'))).':item:'.$itemId,
                    'display_item_id' => $itemId,
                    'title' => $item['name'] ?? $offer['title'] ?? 'Promotion item',
                    'subtitle' => $offer['title'] ?? $offer['subtitle'] ?? $offer['description'] ?? 'Special offer',
                    'menu_items' => [$item],
                ]);
            })
            ->values()
            ->all();
    })->take(12)->values();
@endphp

@if($displayItems->isNotEmpty())
    <section class="restaurants-section">
        <div class="container">
            <div class="rounded-5 p-4 bg-white shadow-sm border">
                <div class="section-header text-start mb-4">
                    <h2 class="section-title" style="text-align:left;">{!! $section['title'] ?? 'Promotions' !!}</h2>
                    @if(!empty($section['subtitle']))
                        <p class="section-subtitle" style="text-align:left;">{{ $section['subtitle'] }}</p>
                    @endif
                </div>
                <div class="row g-4">
                    @foreach($displayItems as $offer)
                        @php
                            $menu = $menuItems($offer);
                            $firstItem = $menu->first();
                            $image = $imageFor($offer);
                            $restaurantId = $firstItem['restaurant_id'] ?? $offer['restaurant_id'] ?? null;
                            $title = $offer['title'] ?? $firstItem['name'] ?? 'Special offer';
                            $subtitle = $firstItem['description'] ?? $offer['subtitle'] ?? $offer['description'] ?? 'Eligible promotion item';
                            $price = (float) ($firstItem['discounted_price'] ?? $firstItem['price'] ?? $offer['discount_value'] ?? 0);
                            $originalPrice = (float) ($firstItem['price'] ?? $price);
                        @endphp
                        <div class="col-sm-6 col-lg-4">
                            <a href="{{ $restaurantId ? url('/restaurants/'.$restaurantId) : url('/offers') }}" class="text-decoration-none">
                                <div class="h-100 rounded-4 overflow-hidden border bg-white shadow-sm">
                                    <div class="position-relative" style="height:190px;background:#f8fafc;">
                                        @if($image)
                                            <img src="{{ $image }}" alt="{{ $title }}" class="w-100 h-100" style="object-fit:cover;">
                                        @else
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#16a34a,#f97316);">
                                                <i class="fas fa-gift text-white fa-3x"></i>
                                            </div>
                                        @endif
                                        <span class="position-absolute top-0 start-0 m-3 badge rounded-pill bg-danger">{{ $rewardText($offer) }}</span>
                                    </div>
                                    <div class="p-3">
                                        <h5 class="fw-bold text-dark mb-1 text-truncate">{{ $title }}</h5>
                                        <p class="text-muted small mb-3" style="min-height:38px;">{{ \Illuminate\Support\Str::limit($subtitle, 78) }}</p>
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <div>
                                                @if($price > 0)
                                                    <span class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($price, $currencyDecimals) }}</span>
                                                    @if($originalPrice > $price)
                                                        <span class="text-muted text-decoration-line-through small ms-1">{{ $currencySymbol }}{{ number_format($originalPrice, $currencyDecimals) }}</span>
                                                    @endif
                                                @else
                                                    <span class="fw-bold text-success">Auto applies</span>
                                                @endif
                                            </div>
                                            <span class="btn btn-sm btn-success rounded-pill px-3">Add</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
