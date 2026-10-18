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
            <div class="section-header text-start mb-4">
                <h2 class="section-title" style="text-align:left;">{!! $section['title'] ?? 'Promotions' !!}</h2>
                @if(!empty($section['subtitle']))
                    <p class="section-subtitle" style="text-align:left;">{{ $section['subtitle'] }}</p>
                @endif
            </div>
            <div class="offer-tile-row">
                @foreach($displayItems as $offer)
                    @php
                        $menu = $menuItems($offer);
                        $firstItem = $menu->first();
                        $image = $imageFor($offer);
                        $restaurantId = $firstItem['restaurant_id'] ?? $offer['restaurant_id'] ?? null;
                        $title = $offer['title'] ?? $firstItem['name'] ?? 'Special offer';
                        $couponCode = $offer['coupon_code'] ?? $offer['code'] ?? null;
                    @endphp
                    <a href="{{ $restaurantId ? route('restaurant.show', $restaurantId) : url('/offers') }}" class="offer-tile">
                        @if($image)
                            <img src="{{ $image }}" alt="{{ $title }}" width="310" height="180" loading="lazy" class="offer-tile-img">
                        @else
                            <div class="offer-tile-img offer-tile-img-fallback">
                                <i class="fas fa-gift"></i>
                            </div>
                        @endif
                        <div class="offer-tile-overlay"></div>
                        <div class="offer-tile-content">
                            <div class="offer-tile-reward">{{ $rewardText($offer) }}</div>
                            <div class="offer-tile-title">{{ \Illuminate\Support\Str::limit($title, 40) }}</div>
                            @if($couponCode)
                                <span class="offer-tile-coupon">CODE {{ $couponCode }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

<style>
    .offer-tile-row {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        padding-bottom: 8px;
        scrollbar-width: none;
    }

    .offer-tile-row::-webkit-scrollbar {
        display: none;
    }

    .offer-tile {
        position: relative;
        flex: 0 0 310px;
        height: 180px;
        border-radius: 18px;
        overflow: hidden;
        text-decoration: none;
        display: block;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    }

    .offer-tile-img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .offer-tile-img-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: #fff;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
    }

    .offer-tile-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(0,0,0,0.72) 0%, rgba(0,0,0,0.25) 55%, rgba(0,0,0,0.08) 100%);
    }

    .offer-tile-content {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 6px;
        padding: 16px 18px;
        color: #fff;
    }

    .offer-tile-reward {
        font-family: 'Nunito Sans', sans-serif;
        font-size: 26px;
        font-weight: 900;
        line-height: 1.1;
    }

    .offer-tile-title {
        font-size: 13px;
        font-weight: 600;
        opacity: 0.92;
    }

    .offer-tile-coupon {
        display: inline-block;
        margin-top: 4px;
        background: #fff;
        color: #1C1C1C;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.02em;
        padding: 4px 10px;
        border-radius: 9px;
        width: fit-content;
    }
</style>
