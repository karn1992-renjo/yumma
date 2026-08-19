@extends('layouts.admin')

@section('title', $promotion->exists ? 'Edit Promotion' : 'Create Promotion')
@section('header', $promotion->exists ? 'Edit Promotion' : 'Create Promotion')

@section('styles')
<style>
    .promo-form {
        --promo-accent: #f97316;
        --promo-accent-dark: #ea580c;
        --promo-ink: #111827;
        --promo-muted: #667085;
        --promo-line: #e5e7eb;
        --promo-soft: #fff7ed;
    }

    .promo-form .page-header {
        background: linear-gradient(135deg, #fff7ed, #ffffff);
        border: 1px solid #fed7aa;
        border-radius: 18px;
        padding: 22px;
        box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
    }

    .promo-form .form-shell {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr) 320px;
        gap: 22px;
        align-items: start;
    }

    .promo-form .wizard-rail {
        position: sticky;
        top: 92px;
        background: #fff;
        border: 1px solid var(--promo-line);
        border-radius: 16px;
        box-shadow: 0 14px 36px rgba(15, 23, 42, .06);
        padding: 12px;
    }

    .promo-form .wizard-step-button {
        width: 100%;
        border: 0;
        background: transparent;
        border-radius: 13px;
        padding: 11px;
        color: #475467;
        display: grid;
        grid-template-columns: 34px 1fr;
        gap: 10px;
        align-items: center;
        text-align: left;
        cursor: pointer;
        transition: .18s ease;
    }

    .promo-form .wizard-step-button + .wizard-step-button {
        margin-top: 5px;
    }

    .promo-form .wizard-step-index {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: #f2f4f7;
        color: #475467;
        font-size: 12px;
        font-weight: 950;
    }

    .promo-form .wizard-step-title {
        display: block;
        color: #101828;
        font-size: 13px;
        font-weight: 950;
        line-height: 1.15;
    }

    .promo-form .wizard-step-copy {
        display: block;
        color: var(--promo-muted);
        font-size: 11px;
        font-weight: 700;
        margin-top: 2px;
    }

    .promo-form .wizard-step-button.is-active {
        background: linear-gradient(135deg, var(--promo-soft), #fff);
        box-shadow: inset 0 0 0 1px #fed7aa;
    }

    .promo-form .wizard-step-button.is-active .wizard-step-index,
    .promo-form .wizard-step-button.is-complete .wizard-step-index {
        color: #fff;
        background: linear-gradient(135deg, var(--promo-accent), #ef4444);
        box-shadow: 0 10px 18px rgba(249, 115, 22, .22);
    }

    .promo-form .wizard-step-button.is-complete .wizard-step-index::before {
        content: "\f00c";
        font-family: "Font Awesome 5 Free";
        font-weight: 900;
    }

    .promo-form .wizard-step-button.is-complete .wizard-step-index span {
        display: none;
    }

    .promo-form .wizard-progress {
        height: 8px;
        border-radius: 999px;
        background: #f2f4f7;
        overflow: hidden;
        margin: 12px 2px 4px;
    }

    .promo-form .wizard-progress-bar {
        height: 100%;
        width: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--promo-accent), #ef4444);
        transition: width .22s ease;
    }

    .promo-form [data-wizard-panel] {
        display: none;
    }

    .promo-form [data-wizard-panel].is-active {
        display: block;
        animation: promoWizardIn .18s ease;
    }

    .promo-form .wizard-mobile-step {
        display: none;
        margin-bottom: 12px;
        color: var(--promo-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .promo-form .wizard-actions {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 18px;
    }

    .promo-form .wizard-actions .btn {
        min-width: 118px;
        font-weight: 900;
    }

    @keyframes promoWizardIn {
        from {
            transform: translateY(8px);
            opacity: .5;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .promo-form .form-card {
        background: #fff;
        border: 1px solid var(--promo-line);
        border-radius: 16px;
        box-shadow: 0 14px 36px rgba(15, 23, 42, .06);
        overflow: hidden;
        margin-bottom: 18px;
    }

    .promo-form .form-card-header {
        padding: 18px 20px;
        border-bottom: 1px solid var(--promo-line);
        background: #fff;
    }

    .promo-form .form-card-body {
        padding: 20px;
    }

    .promo-form .section-title {
        margin: 0;
        color: var(--promo-ink);
        font-weight: 950;
        letter-spacing: -.02em;
    }

    .promo-form .section-subtitle {
        margin: 5px 0 0;
        color: var(--promo-muted);
        font-size: 13px;
        font-weight: 600;
    }

    .promo-form .form-label {
        color: #344054;
        font-size: 12px;
        font-weight: 900;
    }

    .promo-form .required-dot {
        color: #ef4444;
    }

    .promo-form .mode-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .promo-form .mode-tabs input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .promo-form .mode-tabs label {
        border: 1px solid var(--promo-line);
        border-radius: 12px;
        padding: 10px 14px;
        cursor: pointer;
        color: #344054;
        font-weight: 900;
        font-size: 12px;
        background: #fff;
    }

    .promo-form .mode-tabs input:checked + label {
        color: #fff;
        border-color: transparent;
        background: linear-gradient(135deg, var(--promo-accent), #ef4444);
        box-shadow: 0 10px 22px rgba(249, 115, 22, .18);
    }

    .promo-form .upload-tile {
        min-height: 128px;
        display: grid;
        place-items: center;
        text-align: center;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        background: #fcfcfd;
        color: var(--promo-muted);
        cursor: pointer;
        overflow: hidden;
        position: relative;
    }

    .promo-form .upload-tile img {
        display: none;
        width: 100%;
        height: 128px;
        object-fit: cover;
    }

    .promo-form .upload-tile.has-image img {
        display: block;
    }

    .promo-form .upload-tile.has-image .upload-placeholder {
        display: none;
    }

    .promo-form .smart-select input {
        margin-bottom: 8px;
    }

    .promo-form .smart-select select {
        min-height: 180px;
    }

    .promo-form .summary-card {
        position: sticky;
        top: 92px;
    }

    .promo-form .summary-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 11px 0;
        border-bottom: 1px solid #f2f4f7;
        font-size: 13px;
    }

    .promo-form .summary-row span:first-child {
        color: var(--promo-muted);
        font-weight: 800;
    }

    .promo-form .summary-row span:last-child {
        color: var(--promo-ink);
        font-weight: 900;
        text-align: right;
    }

    .promo-form .helper-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 10px;
        border-radius: 999px;
        background: var(--promo-soft);
        color: #9a3412;
        font-size: 12px;
        font-weight: 900;
    }

    @media (max-width: 1180px) {
        .promo-form .form-shell {
            grid-template-columns: 1fr;
        }

        .promo-form .summary-card {
            position: static;
        }

        .promo-form .wizard-rail {
            position: static;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }

        .promo-form .wizard-step-button + .wizard-step-button {
            margin-top: 0;
        }
    }

    @media (max-width: 720px) {
        .promo-form .wizard-rail {
            display: none;
        }

        .promo-form .wizard-mobile-step {
            display: block;
        }
    }
</style>
@endsection

@section('content')
@php
    $reward = (array) ($promotion->rewards ?? []);
    $conditions = (array) ($promotion->conditions ?? []);
    $targets = (array) ($promotion->targets ?? []);
    $schedule = (array) ($promotion->schedule ?? []);
    $stacking = (array) ($promotion->stacking ?? []);
    $visibility = (array) ($promotion->visibility ?? []);

    $adminPromotionTypes = $adminPromotionTypes ?? \App\Support\PromotionTypeRegistry::adminTypes();
    $restaurantPromotionTypes = $restaurantPromotionTypes ?? \App\Support\PromotionTypeRegistry::restaurantTypes();
    $allPromotionTypes = $adminPromotionTypes + $restaurantPromotionTypes;
    $currentOwner = old('owner_type', $promotion->owner_type ?: 'admin');
    $currentPromotionType = \App\Support\PromotionTypeRegistry::normalize(old('promotion_type', $promotion->promotion_type));
    if (! array_key_exists($currentPromotionType, $allPromotionTypes)) {
        $currentPromotionType = array_key_first($allPromotionTypes);
    }
    $currentRewardType = old('reward_type', $reward['type'] ?? ($allPromotionTypes[$currentPromotionType]['reward_type'] ?? 'percentage'));
    $currentMode = old('application_mode', $promotion->application_mode ?: 'automatic');

    $selectedRewardItemIds = collect(old('reward_item_ids', $reward['item_ids'] ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedRewardCategoryIds = collect(old('reward_category_ids', $reward['category_ids'] ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedTargetZoneIds = collect(old('target_zone_ids', data_get($targets, 'zone_ids', [])))->map(fn ($id) => (int) $id)->all();
    $selectedTargetCuisineIds = collect(old('target_cuisine_ids', data_get($targets, 'cuisine_ids', [])))->map(fn ($id) => (int) $id)->all();
    $selectedTargetSubcategoryIds = collect(old('target_subcategory_ids', data_get($targets, 'subcategory_ids', data_get($conditions, 'contains_subcategory_ids', []))))->map(fn ($id) => (int) $id)->all();
    $selectedContainsItemIds = collect(old('contains_item_ids', data_get($conditions, 'contains_item_ids', [])))->map(fn ($id) => (int) $id)->all();
    $selectedExcludeItemIds = collect(old('exclude_item_ids', data_get($conditions, 'exclude_item_ids', [])))->map(fn ($id) => (int) $id)->all();
    $selectedContainsCategoryIds = collect(old('contains_category_ids', data_get($conditions, 'contains_category_ids', [])))->map(fn ($id) => (int) $id)->all();
    $selectedExcludeCategoryIds = collect(old('exclude_category_ids', data_get($conditions, 'exclude_category_ids', [])))->map(fn ($id) => (int) $id)->all();
    $selectedFreeItemId = old('free_item_id', $reward['free_item_id'] ?? null);
    $comboGroups = collect(old('combo_groups', data_get($reward, 'combo_groups', [])))
        ->filter(fn ($group) => is_array($group))
        ->values()
        ->map(fn ($group, $index) => [
            'name' => (string) data_get($group, 'name', 'Combo '.($index + 1)),
            'price' => data_get($group, 'price'),
            'actual_price' => data_get($group, 'actual_price'),
            'discount_percent' => data_get($group, 'discount_percent'),
            'item_ids' => collect(data_get($group, 'item_ids', []))->map(fn ($id) => (int) $id)->filter()->values()->all(),
        ])
        ->all();
    if ($comboGroups === []) {
        $comboGroups = [['name' => 'Combo 1', 'price' => null, 'item_ids' => []]];
    }
    $cuisines = $cuisines ?? collect();
    $globalSubcategories = $globalSubcategories ?? collect();
    $subcategoryIdByName = collect($globalSubcategories)
        ->mapWithKeys(fn ($subcategory) => [strtolower(trim((string) $subcategory->name)) => (int) $subcategory->id])
        ->all();

    $showOnDefault = ['home', 'restaurant', 'offers', 'cart', 'checkout'];
    $selectedVisibility = (array) old('visibility', data_get($visibility, 'show_on', $showOnDefault));
    $promotionImageUrls = [
        'promotion_image' => $promotion->image_url,
        'banner_image' => \App\Services\MediaStorage::url(data_get($visibility, 'banner_image')),
        'thumbnail' => \App\Services\MediaStorage::url(data_get($visibility, 'thumbnail')),
        'campaign_banner' => \App\Services\MediaStorage::url(data_get($visibility, 'campaign_banner')),
    ];

    $csv = function ($value): string {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map('strval', $value)));
        }

        return trim((string) ($value ?? ''));
    };

    $checked = fn (bool $state): string => $state ? 'checked' : '';
    $selected = fn (bool $state): string => $state ? 'selected' : '';

    $rewardTypes = [
        'percentage' => 'Percentage Discount',
        'flat' => 'Flat Discount',
        'fixed_price' => 'Fixed Price',
        'free_delivery' => 'Free Delivery',
        'delivery_discount' => 'Delivery Discount',
        'packaging_discount' => 'Packaging Discount',
        'item_discount' => 'Item Discount',
        'category_discount' => 'Category Discount',
        'combo_deal' => 'Combo Deal',
        'meal_deal' => 'Meal Deal',
        'bogo' => 'BOGO',
        'buy_x_get_y' => 'Buy X Get Y',
        'buy_2_get_1' => 'Buy 2 Get 1',
        'buy_3_get_1' => 'Buy 3 Get 1',
        'buy_3_get_2' => 'Buy 3 Get 2',
        'free_item' => 'Free Item',
        'wallet_credit' => 'Wallet Cashback',
        'reward_points' => 'Reward Points',
        'scratch_card' => 'Scratch Card',
        'gift_voucher' => 'Gift Voucher',
        'referral_bonus' => 'Referral Bonus',
        'custom_rule' => 'Custom Rule',
    ];

    $legacyRewardValue = (float) ($reward['value'] ?? 0);
    $rewardValueType = old(
        'reward_value_type',
        $reward['value_type'] ?? ($legacyRewardValue > 100 ? 'fixed' : 'percentage')
    );

    $scratchSettings = (array) ($reward['settings'] ?? []);
    $defaultScratchRows = collect($reward['pool'] ?? [])
        ->filter(fn ($row) => is_array($row))
        ->values()
        ->map(fn ($row, $index) => [
            'name' => (string) ($row['name'] ?? ''),
            'type' => (string) ($row['type'] ?? 'wallet_cashback'),
            'value' => (string) ($row['value'] ?? ''),
            'probability' => (string) ($row['probability'] ?? ''),
            'max_redemptions' => (string) ($row['max_redemptions'] ?? ''),
            'daily_limit' => (string) ($row['daily_limit'] ?? ''),
            'budget' => (string) ($row['budget'] ?? ''),
            'expiry_days' => (string) ($row['expiry_days'] ?? ''),
            'priority' => (string) ($row['priority'] ?? ($index + 1)),
            'metadata' => ! empty($row['metadata'] ?? []) ? json_encode($row['metadata'], JSON_UNESCAPED_SLASHES) : '',
        ])
        ->all();
    $scratchRows = old('scratch_rewards', $defaultScratchRows ?: [
        ['name' => 'Wallet Cashback', 'type' => 'wallet_cashback', 'value' => 50, 'probability' => 35, 'max_redemptions' => '', 'daily_limit' => '', 'budget' => '', 'expiry_days' => 30, 'priority' => 1, 'metadata' => ''],
        ['name' => 'Reward Points', 'type' => 'reward_points', 'value' => 100, 'probability' => 25, 'max_redemptions' => '', 'daily_limit' => '', 'budget' => '', 'expiry_days' => 30, 'priority' => 2, 'metadata' => ''],
        ['name' => 'Free Delivery Coupon', 'type' => 'free_delivery', 'value' => 0, 'probability' => 20, 'max_redemptions' => '', 'daily_limit' => '', 'budget' => '', 'expiry_days' => 30, 'priority' => 3, 'metadata' => ''],
        ['name' => 'Better Luck Next Time', 'type' => 'no_reward', 'value' => 0, 'probability' => 20, 'max_redemptions' => '', 'daily_limit' => '', 'budget' => '', 'expiry_days' => 30, 'priority' => 4, 'metadata' => ''],
    ]);

    $typeCatalog = [
        'admin' => $adminPromotionTypes,
        'restaurant' => $restaurantPromotionTypes,
    ];
    $typeBucketByPromotion = collect($adminPromotionTypes)
        ->mapWithKeys(fn ($meta, $type) => [$type => 'admin'])
        ->merge(collect($restaurantPromotionTypes)->mapWithKeys(fn ($meta, $type) => [$type => 'restaurant']))
        ->all();
    $rewardByPromotion = collect($adminPromotionTypes + $restaurantPromotionTypes)
        ->mapWithKeys(fn ($meta, $type) => [$type => $meta['reward_type'] ?? 'percentage'])
        ->all();
@endphp

<div class="promo-form">
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h1 class="mb-1">{{ $promotion->exists ? 'Edit Promotion' : 'Create Promotion' }}</h1>
                <p class="mb-0">Create platform and restaurant promotions with mapped rewards, targeting, coupon, and media settings.</p>
            </div>
            <a href="{{ route('admin.promotion-engine.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-2"><i class="fas fa-circle-exclamation me-2"></i>Please fix these fields:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ $promotion->exists ? route('admin.promotion-engine.update', $promotion) : route('admin.promotion-engine.store') }}" data-promotion-form>
        @csrf
        @if($promotion->exists)
            @method('PUT')
        @endif

        <input type="hidden" name="reward_type" value="{{ $currentRewardType }}" data-reward-type>

        <div class="form-shell">
            <aside class="wizard-rail" data-wizard-rail>
                <button class="wizard-step-button" type="button" data-wizard-tab="0">
                    <span class="wizard-step-index"><span>1</span></span>
                    <span><span class="wizard-step-title">General</span><span class="wizard-step-copy">Name, owner, status</span></span>
                </button>
                <button class="wizard-step-button" type="button" data-wizard-tab="1">
                    <span class="wizard-step-index"><span>2</span></span>
                    <span><span class="wizard-step-title">Workflow</span><span class="wizard-step-copy">Type and apply mode</span></span>
                </button>
                <button class="wizard-step-button" type="button" data-wizard-tab="2">
                    <span class="wizard-step-index"><span>3</span></span>
                    <span><span class="wizard-step-title">Reward</span><span class="wizard-step-copy">Values and mapped items</span></span>
                </button>
                <button class="wizard-step-button" type="button" data-wizard-tab="3" data-scratch-tab>
                    <span class="wizard-step-index"><span>4</span></span>
                    <span><span class="wizard-step-title">Scratch Pool</span><span class="wizard-step-copy">Reward probability</span></span>
                </button>
                <button class="wizard-step-button" type="button" data-wizard-tab="4">
                    <span class="wizard-step-index"><span>5</span></span>
                    <span><span class="wizard-step-title">Audience</span><span class="wizard-step-copy">Zones and conditions</span></span>
                </button>
                <button class="wizard-step-button" type="button" data-wizard-tab="5">
                    <span class="wizard-step-index"><span>6</span></span>
                    <span><span class="wizard-step-title">Display</span><span class="wizard-step-copy">Date, images, coupon</span></span>
                </button>
                <button class="wizard-step-button" type="button" data-wizard-tab="6">
                    <span class="wizard-step-index"><span>7</span></span>
                    <span><span class="wizard-step-title">Funding</span><span class="wizard-step-copy">Budget, fraud, liability</span></span>
                </button>
                <button class="wizard-step-button" type="button" data-wizard-tab="7">
                    <span class="wizard-step-index"><span>8</span></span>
                    <span><span class="wizard-step-title">Review</span><span class="wizard-step-copy">Confirm and save</span></span>
                </button>
                <div class="wizard-progress"><div class="wizard-progress-bar" data-wizard-progress></div></div>
            </aside>

            <main>
                <div class="wizard-mobile-step" data-wizard-mobile-step></div>

                <section class="form-card" data-wizard-panel="0">
                    <div class="form-card-header">
                        <h5 class="section-title">General</h5>
                        <p class="section-subtitle">Basic campaign identity and ownership.</p>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <label class="form-label">Title <span class="required-dot">*</span></label>
                                <input class="form-control" name="title" value="{{ old('title', $promotion->title) }}" required data-summary-field="title">
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Status <span class="required-dot">*</span></label>
                                <select class="form-select" name="status" required data-summary-field="status">
                                    @foreach(['draft', 'active', 'paused'] as $status)
                                        <option value="{{ $status }}" {{ $selected(old('status', $promotion->status ?: 'draft') === $status) }}>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Owner <span class="required-dot">*</span></label>
                                <select class="form-select" name="owner_type" required data-owner data-summary-field="owner">
                                    @foreach(['admin', 'restaurant', 'branch', 'system', 'ai'] as $owner)
                                        <option value="{{ $owner }}" {{ $selected($currentOwner === $owner) }}>{{ ucfirst($owner) }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Admin types and restaurant types stay separate.</div>
                            </div>
                            <div class="col-lg-8">
                                <label class="form-label">Priority</label>
                                <input class="form-control" type="number" min="1" max="9999" name="priority" value="{{ old('priority', $promotion->priority ?: 100) }}" data-summary-field="priority">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" rows="3" maxlength="2000" name="description">{{ old('description', $promotion->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="form-card" data-wizard-panel="1">
                    <div class="form-card-header">
                        <h5 class="section-title">Promotion Type</h5>
                        <p class="section-subtitle">Choose the workflow. The reward type is mapped automatically.</p>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <label class="form-label">Promotion Type <span class="required-dot">*</span></label>
                                <select class="form-select" name="promotion_type" required data-promotion-type data-summary-field="promotion_type">
                                    <optgroup label="Admin / Platform Offers">
                                        @foreach($adminPromotionTypes as $type => $meta)
                                            <option value="{{ $type }}" {{ $selected($currentPromotionType === $type) }}>{{ $meta['label'] ?? ucwords(str_replace('_', ' ', $type)) }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Restaurant / Menu Offers">
                                        @foreach($restaurantPromotionTypes as $type => $meta)
                                            <option value="{{ $type }}" {{ $selected($currentPromotionType === $type) }}>{{ $meta['label'] ?? ucwords(str_replace('_', ' ', $type)) }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                                <div class="form-text" data-type-help></div>
                            </div>
                            <div class="col-lg-5">
                                <label class="form-label">Application Mode <span class="required-dot">*</span></label>
                                <div class="mode-tabs">
                                    <input type="radio" id="mode-automatic" name="application_mode" value="automatic" {{ $checked($currentMode === 'automatic') }} data-application-mode>
                                    <label for="mode-automatic">Automatic</label>
                                    <input type="radio" id="mode-coupon" name="application_mode" value="coupon" {{ $checked($currentMode === 'coupon') }} data-application-mode>
                                    <label for="mode-coupon">Coupon</label>
                                </div>
                                <div class="form-text" data-mode-help></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="form-card" data-wizard-panel="2">
                    <div class="form-card-header">
                        <h5 class="section-title">Reward Configuration</h5>
                        <p class="section-subtitle">Only fields relevant to the selected promotion type are used.</p>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-12" data-reward-field="menu-finder">
                                <div class="p-3 rounded-3 border bg-light">
                                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
                                        <div>
                                            <h6 class="mb-1 fw-bold">Menu Finder</h6>
                                            <div class="small text-muted">Filter in order: cuisine/category/subcategory, then restaurant, then mapped menu.</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-clear-menu-filters>
                                            <i class="fas fa-rotate-left me-1"></i>Reset filters
                                        </button>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label">Cuisine</label>
                                            <select class="form-select" name="target_cuisine_ids[]" multiple size="6" data-menu-filter-cuisine data-target-cuisine-select>
                                                @foreach($cuisines as $cuisine)
                                                    <option value="{{ $cuisine->id }}" {{ $selected(in_array((int) $cuisine->id, $selectedTargetCuisineIds, true)) }}>{{ $cuisine->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label">Category</label>
                                            <select class="form-select" name="reward_category_ids[]" multiple size="6" data-menu-filter-category data-category-select>
                                                @foreach($categories as $category)
                                                    @php
                                                        $filterCategoryLabel = $category->name . ($category->restaurant ? ' - '.$category->restaurant->name : '');
                                                    @endphp
                                                    <option value="{{ $category->id }}" data-restaurant-id="{{ $category->restaurant_id }}" {{ $selected(in_array((int) $category->id, $selectedRewardCategoryIds, true)) }}>{{ $filterCategoryLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label">Subcategory</label>
                                            <select class="form-select" name="target_subcategory_ids[]" multiple size="6" data-menu-filter-subcategory data-target-subcategory-select>
                                                @foreach($globalSubcategories as $subcategory)
                                                    @php
                                                        $filterSubcategoryLabel = ($subcategory->parent ? $subcategory->parent->name.' - ' : '').$subcategory->name;
                                                    @endphp
                                                    <option value="{{ $subcategory->id }}" {{ $selected(in_array((int) $subcategory->id, $selectedTargetSubcategoryIds, true)) }}>{{ $filterSubcategoryLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-3 col-md-6" data-restaurant-scope>
                                            <label class="form-label">Restaurant <span class="required-dot" data-restaurant-required-dot>*</span></label>
                                            <select class="form-select" name="restaurant_id" data-restaurant data-summary-field="restaurant">
                                                <option value="">Select restaurant</option>
                                                @foreach($restaurants as $restaurant)
                                                    <option value="{{ $restaurant->id }}" data-filterable-restaurant {{ $selected((int) old('restaurant_id', $promotion->restaurant_id) === (int) $restaurant->id) }}>{{ $restaurant->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="form-text" data-restaurant-help>Select cuisine/category/subcategory first to narrow restaurants.</div>
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-2" data-menu-filter-help>Showing all restaurants and menu items.</div>
                                </div>
                            </div>
                            <div class="col-md-4" data-reward-field="value">
                                <label class="form-label">Value</label>
                                <input class="form-control" type="number" step="0.01" min="0" name="reward_value" value="{{ old('reward_value', $reward['value'] ?? null) }}" data-summary-field="value">
                                <div class="form-text" data-value-help></div>
                            </div>
                            <div class="col-md-4" data-reward-field="value-mode">
                                <label class="form-label">Value Type</label>
                                <select class="form-select" name="reward_value_type">
                                    <option value="percentage" {{ $selected($rewardValueType === 'percentage') }}>Percentage</option>
                                    <option value="fixed" {{ $selected($rewardValueType === 'fixed') }}>Fixed amount</option>
                                </select>
                                <div class="form-text">Choose how the charge discount value is calculated.</div>
                            </div>
                            <div class="col-md-4" data-reward-field="max-discount">
                                <label class="form-label">Maximum Discount</label>
                                <input class="form-control" type="number" step="0.01" min="0" name="max_discount" value="{{ old('max_discount', $reward['max_discount'] ?? null) }}">
                            </div>
                            <div class="col-md-2" data-reward-field="buy-free">
                                <label class="form-label">Buy Qty</label>
                                <input class="form-control" type="number" min="1" name="buy_quantity" value="{{ old('buy_quantity', $reward['buy_quantity'] ?? 1) }}">
                            </div>
                            <div class="col-md-2" data-reward-field="buy-free">
                                <label class="form-label">Free Qty</label>
                                <input class="form-control" type="number" min="1" name="free_quantity" value="{{ old('free_quantity', $reward['free_quantity'] ?? 1) }}">
                            </div>
                            <div class="col-lg-6" data-reward-field="items">
                                <label class="form-label">Mapped Menu Items</label>
                                <div class="smart-select">
                                    <input class="form-control" type="search" placeholder="Search items" data-smart-search>
                                    <select class="form-select" name="reward_item_ids[]" multiple size="9" data-item-select data-smart-options>
                                        @foreach($menuItems as $menuItem)
                                            @php
                                                $parts = [$menuItem->name];
                                                if ($menuItem->restaurant) {
                                                    $parts[] = $menuItem->restaurant->name;
                                                }
                                                if ($menuItem->category) {
                                                    $parts[] = $menuItem->category->name;
                                                }
                                                $parts[] = number_format((float) $menuItem->price, 2);
                                                $menuLabel = implode(' - ', array_filter($parts));
                                            @endphp
                                            @php
                                                $menuSubcategoryId = $subcategoryIdByName[strtolower(trim((string) optional($menuItem->masterMenuItem)->subcategory_name))] ?? '';
                                            @endphp
                                            <option value="{{ $menuItem->id }}" data-restaurant-id="{{ $menuItem->restaurant_id }}" data-category-id="{{ $menuItem->category_id }}" data-cuisine-id="{{ $menuItem->cuisine_id }}" data-subcategory-id="{{ $menuSubcategoryId }}" data-price="{{ (float) $menuItem->price }}" {{ $selected(in_array((int) $menuItem->id, $selectedRewardItemIds, true)) }}>{{ $menuLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-text">Combo/meal needs at least two mapped paid items. BOGO/free item needs at least one eligible item.</div>
                            </div>
                            <div class="col-12" data-reward-field="combo-groups">
                                <div class="border rounded-3 p-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
                                        <div>
                                            <h6 class="mb-1 fw-bold">Combo Groups</h6>
                                            <div class="small text-muted">Create separate combos like A+B, C+D. Customer only needs one complete group.</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-add-combo-group>
                                            <i class="fas fa-plus me-1"></i>Add group
                                        </button>
                                    </div>
                                    <div class="vstack gap-3" data-combo-groups>
                                        @foreach($comboGroups as $groupIndex => $group)
                                            <div class="border rounded-3 p-3 bg-light" data-combo-group>
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Group Name</label>
                                                        <input class="form-control" name="combo_groups[{{ $groupIndex }}][name]" value="{{ data_get($group, 'name') }}">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Actual Total</label>
                                                        <input class="form-control" type="number" step="0.01" min="0" name="combo_groups[{{ $groupIndex }}][actual_price]" value="{{ data_get($group, 'actual_price') }}" data-combo-actual readonly>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Effective Price</label>
                                                        <input class="form-control" type="number" step="0.01" min="0" name="combo_groups[{{ $groupIndex }}][price]" value="{{ data_get($group, 'price') }}" placeholder="Main value" data-combo-price>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Discount %</label>
                                                        <input class="form-control" type="number" step="0.01" min="0" max="100" name="combo_groups[{{ $groupIndex }}][discount_percent]" value="{{ data_get($group, 'discount_percent') }}" data-combo-percent readonly>
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-end justify-content-md-end">
                                                        <button class="btn btn-light text-danger" type="button" data-remove-combo-group>
                                                            <i class="fas fa-trash me-1"></i>Remove
                                                        </button>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Menu Items</label>
                                                        <select class="form-select" name="combo_groups[{{ $groupIndex }}][item_ids][]" multiple size="6" data-combo-group-item-select>
                                                            @foreach($menuItems as $menuItem)
                                                                @php
                                                                    $comboParts = [$menuItem->name];
                                                                    if ($menuItem->restaurant) {
                                                                        $comboParts[] = $menuItem->restaurant->name;
                                                                    }
                                                                    if ($menuItem->category) {
                                                                        $comboParts[] = $menuItem->category->name;
                                                                    }
                                                                    $comboParts[] = number_format((float) $menuItem->price, 2);
                                                                    $comboSubcategoryId = $subcategoryIdByName[strtolower(trim((string) optional($menuItem->masterMenuItem)->subcategory_name))] ?? '';
                                                                @endphp
                                                                <option value="{{ $menuItem->id }}" data-restaurant-id="{{ $menuItem->restaurant_id }}" data-category-id="{{ $menuItem->category_id }}" data-cuisine-id="{{ $menuItem->cuisine_id }}" data-subcategory-id="{{ $comboSubcategoryId }}" data-price="{{ (float) $menuItem->price }}" {{ $selected(in_array((int) $menuItem->id, (array) data_get($group, 'item_ids', []), true)) }}>{{ implode(' - ', array_filter($comboParts)) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6" data-reward-field="free-item">
                                <label class="form-label">Configured Free Item</label>
                                <select class="form-select" name="free_item_id" data-free-item-select>
                                    <option value="">Auto cheapest eligible item</option>
                                    @foreach($menuItems as $menuItem)
                                        @php
                                            $freeParts = [$menuItem->name];
                                            if ($menuItem->restaurant) {
                                                $freeParts[] = $menuItem->restaurant->name;
                                            }
                                            if ($menuItem->category) {
                                                $freeParts[] = $menuItem->category->name;
                                            }
                                            $freeParts[] = number_format((float) $menuItem->price, 2);
                                            $freeLabel = implode(' - ', array_filter($freeParts));
                                        @endphp
                                        @php
                                            $freeSubcategoryId = $subcategoryIdByName[strtolower(trim((string) optional($menuItem->masterMenuItem)->subcategory_name))] ?? '';
                                        @endphp
                                        <option value="{{ $menuItem->id }}" data-restaurant-id="{{ $menuItem->restaurant_id }}" data-category-id="{{ $menuItem->category_id }}" data-cuisine-id="{{ $menuItem->cuisine_id }}" data-subcategory-id="{{ $freeSubcategoryId }}" {{ $selected((string) $selectedFreeItemId === (string) $menuItem->id) }}>{{ $freeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6" data-reward-field="referral">
                                <label class="form-label">Referral Bonus Type</label>
                                <select class="form-select" name="referral_bonus_type">
                                    @foreach(['wallet_credit' => 'Wallet Credit', 'reward_points' => 'Reward Points', 'gift_voucher' => 'Gift Voucher'] as $value => $label)
                                        <option value="{{ $value }}" {{ $selected(old('referral_bonus_type', $reward['bonus_type'] ?? 'wallet_credit') === $value) }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12" data-reward-field="custom">
                                <label class="form-label">Custom Rule JSON / Notes</label>
                                <textarea class="form-control" rows="4" name="custom_rule_config">{{ old('custom_rule_config', $reward['custom_rule'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="form-card" data-scratch-section data-wizard-panel="3" data-optional-step="scratch">
                    <div class="form-card-header">
                        <h5 class="section-title">Scratch Card Pool</h5>
                        <p class="section-subtitle">Rewards are generated during scratch. Probability total must be 100%.</p>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Trigger</label>
                                <select class="form-select" name="scratch_trigger">
                                    @foreach(['payment_success' => 'After Payment Success', 'order_placed' => 'After Order Placed', 'restaurant_accepts' => 'After Restaurant Accepts', 'delivery' => 'After Delivery', 'every_order' => 'Every Eligible Order', 'every_n_orders' => 'Every N Orders', 'manual' => 'Manual Issue'] as $value => $label)
                                        <option value="{{ $value }}" {{ $selected(old('scratch_trigger', data_get($scratchSettings, 'trigger', 'payment_success')) === $value) }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Every N</label>
                                <input class="form-control" type="number" min="1" max="1000" name="scratch_every_n_orders" value="{{ old('scratch_every_n_orders', data_get($scratchSettings, 'every_n_orders')) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Reward Timing</label>
                                <select class="form-select" name="scratch_generate_reward_timing">
                                    <option value="during_scratch" {{ $selected(old('scratch_generate_reward_timing', data_get($scratchSettings, 'generate_reward_timing', 'during_scratch')) === 'during_scratch') }}>Generate During Scratch</option>
                                    <option value="on_card_creation" {{ $selected(old('scratch_generate_reward_timing', data_get($scratchSettings, 'generate_reward_timing')) === 'on_card_creation') }}>Generate On Card Creation</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Card Expiry Days</label>
                                <input class="form-control" type="number" min="1" max="365" name="scratch_card_expiry_days" value="{{ old('scratch_card_expiry_days', data_get($scratchSettings, 'expiry_days', 30)) }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Reward Expiry Days</label>
                                <input class="form-control" type="number" min="1" max="365" name="scratch_reward_expiry_days" value="{{ old('scratch_reward_expiry_days', data_get($scratchSettings, 'reward_expiry_days', 30)) }}">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-2" data-scratch-table>
                                <thead>
                                    <tr>
                                        <th>Reward</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>Probability</th>
                                        <th>Priority</th>
                                        <th>Max</th>
                                        <th>Daily</th>
                                        <th>Budget</th>
                                        <th>Expiry</th>
                                        <th>Metadata</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($scratchRows as $index => $row)
                                        <tr data-scratch-row>
                                            <td><input class="form-control" name="scratch_rewards[{{ $index }}][name]" value="{{ data_get($row, 'name') }}"></td>
                                            <td>
                                                <select class="form-select" name="scratch_rewards[{{ $index }}][type]">
                                                    @foreach(['wallet_cashback' => 'Wallet Cashback', 'reward_points' => 'Reward Points', 'free_delivery' => 'Free Delivery', 'flat_discount' => 'Flat Coupon', 'percentage_discount' => 'Percentage Coupon', 'buy_x_get_y' => 'Buy X Get Y', 'free_item' => 'Free Item', 'gift_voucher' => 'Gift Voucher', 'no_reward' => 'Better Luck'] as $value => $label)
                                                        <option value="{{ $value }}" {{ $selected(data_get($row, 'type') === $value) }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input class="form-control" type="number" step="0.01" min="0" name="scratch_rewards[{{ $index }}][value]" value="{{ data_get($row, 'value') }}"></td>
                                            <td><input class="form-control" type="number" step="0.01" min="0" max="100" name="scratch_rewards[{{ $index }}][probability]" value="{{ data_get($row, 'probability') }}" data-probability></td>
                                            <td><input class="form-control" type="number" min="1" name="scratch_rewards[{{ $index }}][priority]" value="{{ data_get($row, 'priority', $index + 1) }}"></td>
                                            <td><input class="form-control" type="number" min="1" name="scratch_rewards[{{ $index }}][max_redemptions]" value="{{ data_get($row, 'max_redemptions') }}"></td>
                                            <td><input class="form-control" type="number" min="1" name="scratch_rewards[{{ $index }}][daily_limit]" value="{{ data_get($row, 'daily_limit') }}"></td>
                                            <td><input class="form-control" type="number" step="0.01" min="0" name="scratch_rewards[{{ $index }}][budget]" value="{{ data_get($row, 'budget') }}"></td>
                                            <td><input class="form-control" type="number" min="1" max="365" name="scratch_rewards[{{ $index }}][expiry_days]" value="{{ data_get($row, 'expiry_days') }}"></td>
                                            <td><input class="form-control" name="scratch_rewards[{{ $index }}][metadata]" value="{{ data_get($row, 'metadata') }}"></td>
                                            <td><button class="btn btn-light text-danger" type="button" data-remove-scratch-row><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <span class="helper-pill" data-probability-total>Probability total: 0%</span>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-add-scratch-row><i class="fas fa-plus me-1"></i>Add reward</button>
                        </div>
                        <textarea class="form-control mt-3" name="scratch_card_pool" rows="2" placeholder="Legacy one reward per line">{{ old('scratch_card_pool') }}</textarea>
                    </div>
                </section>

                <section class="form-card" data-wizard-panel="4">
                    <div class="form-card-header">
                        <h5 class="section-title">Targeting & Conditions</h5>
                        <p class="section-subtitle">Delivery targeting uses configured zones only.</p>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label">Delivery Zones</label>
                                <select class="form-select" name="target_zone_ids[]" multiple size="7">
                                    @foreach($deliveryAreas as $area)
                                        @php
                                            $zoneParts = [$area->name];
                                            if ($area->area_type) {
                                                $zoneParts[] = ucwords(str_replace('_', ' ', $area->area_type));
                                            }
                                            if ($area->radius_km) {
                                                $zoneParts[] = number_format((float) $area->radius_km, 1).' km';
                                            }
                                            $zoneLabel = implode(' - ', array_filter($zoneParts));
                                        @endphp
                                        <option value="{{ $area->id }}" {{ $selected(in_array((int) $area->id, $selectedTargetZoneIds, true)) }}>{{ $zoneLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Leave blank for all active delivery zones.</div>
                            </div>
                            <div class="col-lg-6">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Audience</label>
                                        <select class="form-select" name="audience_type">
                                            @foreach(['all', 'new_customer', 'returning_customer', 'first_order', 'nth_order', 'vip', 'gold', 'silver', 'premium', 'corporate', 'subscribed', 'wallet_user', 'referral_user', 'birthday', 'anniversary', 'high_spender', 'low_spender', 'inactive_user'] as $audience)
                                                <option value="{{ $audience }}" {{ $selected(old('audience_type', $conditions['audience_type'] ?? 'all') === $audience) }}>{{ ucwords(str_replace('_', ' ', $audience)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Customer Tags</label>
                                        <input class="form-control" name="customer_tags" value="{{ old('customer_tags', $csv(data_get($targets, 'customer_tags', []))) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Specific User IDs</label>
                                        <input class="form-control" name="customer_ids" value="{{ old('customer_ids', $csv(data_get($targets, 'customer_ids', []))) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Min Order Amount</label>
                                        <input class="form-control" type="number" step="0.01" min="0" name="min_order_amount" value="{{ old('min_order_amount', $conditions['min_order_amount'] ?? null) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Max Order Amount</label>
                                        <input class="form-control" type="number" step="0.01" min="0" name="max_order_amount" value="{{ old('max_order_amount', $conditions['max_order_amount'] ?? null) }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Order Types</label>
                                <select class="form-select" name="order_types[]" multiple>
                                    @foreach(['delivery', 'takeaway', 'pickup', 'dine_in'] as $orderType)
                                        <option value="{{ $orderType }}" {{ $selected(in_array($orderType, (array) old('order_types', data_get($targets, 'order_types', [])), true)) }}>{{ ucwords(str_replace('_', ' ', $orderType)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Platforms</label>
                                <select class="form-select" name="platforms[]" multiple>
                                    @foreach(['android', 'ios', 'web', 'pwa', 'pos', 'qr', 'ai'] as $platform)
                                        <option value="{{ $platform }}" {{ $selected(in_array($platform, (array) old('platforms', data_get($targets, 'platforms', [])), true)) }}>{{ strtoupper($platform) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Payment Methods</label>
                                <select class="form-select" name="payment_methods[]" multiple>
                                    @foreach(['cod', 'wallet', 'card', 'upi', 'razorpay', 'stripe', 'cashfree'] as $method)
                                        <option value="{{ $method }}" {{ $selected(in_array($method, (array) old('payment_methods', data_get($conditions, 'payment_methods', [])), true)) }}>{{ strtoupper($method) }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Admin-only condition; restaurant app does not expose it.</div>
                            </div>
                            @foreach(['min_item_count' => 'Min Unique Items', 'min_quantity' => 'Min Quantity', 'max_quantity' => 'Max Quantity', 'min_distance_km' => 'Min Distance KM', 'max_distance_km' => 'Max Distance KM', 'min_delivery_fee' => 'Min Delivery Fee', 'max_delivery_fee' => 'Max Delivery Fee', 'min_packaging_fee' => 'Min Packaging Fee', 'max_packaging_fee' => 'Max Packaging Fee', 'min_tax' => 'Min Tax', 'max_tax' => 'Max Tax', 'min_weight' => 'Min Weight', 'max_weight' => 'Max Weight'] as $field => $label)
                                <div class="col-md-3">
                                    <label class="form-label">{{ $label }}</label>
                                    <input class="form-control" type="number" step="0.01" min="0" name="{{ $field }}" value="{{ old($field, data_get($conditions, $field)) }}">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="form-card" data-wizard-panel="5">
                    <div class="form-card-header">
                        <h5 class="section-title">Schedule, Display & Coupon</h5>
                        <p class="section-subtitle">Control validity, placement, images, and coupon code when required.</p>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input class="form-control" type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($promotion->starts_at)->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input class="form-control" type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($promotion->ends_at)->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start Time</label>
                                <input class="form-control" type="time" name="starts_time" value="{{ old('starts_time', $promotion->starts_time) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Time</label>
                                <input class="form-control" type="time" name="ends_time" value="{{ old('ends_time', $promotion->ends_time) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Weekdays</label>
                                <select class="form-select" name="weekdays[]" multiple>
                                    @foreach([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                                        <option value="{{ $day }}" {{ $selected(in_array($day, (array) old('weekdays', data_get($schedule, 'weekdays', [])), false)) }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Show On</label>
                                <div class="row g-2">
                                    @foreach(['home' => 'Home', 'restaurant' => 'Restaurant', 'offers' => 'Offers', 'cart' => 'Cart', 'checkout' => 'Checkout', 'wallet' => 'Wallet', 'ai' => 'AI Recommendation', 'campaign' => 'Campaign Landing'] as $key => $label)
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="visibility[]" id="visibility-{{ $key }}" value="{{ $key }}" {{ $checked(in_array($key, $selectedVisibility, true)) }}>
                                                <label class="form-check-label fw-semibold" for="visibility-{{ $key }}">{{ $label }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @foreach(['promotion_image' => ['Promotion Image', '1200x400'], 'banner_image' => ['Banner Image', '1200x400'], 'thumbnail' => ['Thumbnail', '600x600']] as $field => $meta)
                                @php
                                    $imageUrl = $promotionImageUrls[$field] ?? null;
                                    $tileClass = 'upload-tile'.($imageUrl ? ' has-image' : '');
                                @endphp
                                <div class="col-md-4">
                                    <label class="form-label">{{ $meta[0] }}</label>
                                    <label class="{{ $tileClass }}" data-upload-tile>
                                        <input class="d-none" type="file" name="{{ $field }}" accept="image/*" data-image-input>
                                        <img src="{{ $imageUrl ?: '' }}" alt="{{ $meta[0] }} preview" data-image-preview>
                                        <span class="upload-placeholder"><i class="fas fa-image d-block mb-2"></i>Upload<br><small>{{ $meta[1] }}</small></span>
                                    </label>
                                </div>
                            @endforeach
                            <div class="col-md-4">
                                <label class="form-label">Color</label>
                                <input class="form-control form-control-color w-100" type="color" name="promotion_color" value="{{ old('promotion_color', data_get($visibility, 'color', '#f97316')) }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Tags</label>
                                <input class="form-control" name="tags" value="{{ old('tags', $csv(data_get($visibility, 'tags', []))) }}">
                            </div>
                            <div class="col-12" data-coupon-section>
                                <div class="row g-3 p-3 rounded-3 border bg-light">
                                    <div class="col-md-4">
                                        <label class="form-label">Coupon Code</label>
                                        <input class="form-control" name="coupon_code" value="{{ old('coupon_code', $couponCode?->code) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Coupon Usage Limit</label>
                                        <input class="form-control" type="number" min="1" name="coupon_usage_limit" value="{{ old('coupon_usage_limit', $couponCode?->usage_limit) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Coupon Preview Prefix</label>
                                        <input class="form-control" name="coupon_prefix" value="{{ old('coupon_prefix') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="form-card" data-wizard-panel="6">
                    <div class="form-card-header">
                        <h5 class="section-title">Funding, Budget & Stacking</h5>
                        <p class="section-subtitle">Set campaign funding, budgets, fraud limits, and stacking behavior.</p>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            @foreach(['campaign_name' => 'Campaign Name', 'landing_page' => 'Landing Page', 'push_notification' => 'Push Notification', 'email_copy' => 'Email', 'sms_copy' => 'SMS', 'whatsapp_copy' => 'WhatsApp', 'campaign_audience' => 'Audience', 'campaign_status' => 'Campaign Status'] as $field => $label)
                                <div class="col-md-6">
                                    <label class="form-label">{{ $label }}</label>
                                    <input class="form-control" name="{{ $field }}" value="{{ old($field, data_get($visibility, $field)) }}">
                                </div>
                            @endforeach
                            <div class="col-md-6" data-budget-field="total">
                                <label class="form-label">Total Campaign Budget</label>
                                <input class="form-control" type="number" step="0.01" min="0" name="campaign_tab_budget" value="{{ old('campaign_tab_budget', $promotion->total_budget ?? data_get($visibility, 'campaign_budget')) }}" data-total-budget>
                                <div class="form-text">Promotion stops applying when total discount burn reaches this amount.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Funding Source</label>
                                <select class="form-select" name="funding_type" data-funding-type data-summary-field="funding">
                                    @foreach(['platform' => 'Platform funded', 'restaurant' => 'Restaurant funded', 'shared' => 'Shared funding', 'bank_partner' => 'Bank/partner funded'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('funding_type', $promotion->funding_type ?: $promotion->resolvedFundingType()) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text" data-funding-help></div>
                            </div>
                            <div class="col-md-3" data-funding-field="platform-share">
                                <label class="form-label">Platform Share %</label>
                                <input class="form-control" type="number" step="0.01" min="0" max="100" name="platform_share_percent" value="{{ old('platform_share_percent', $promotion->platform_share_percent) }}" data-platform-share>
                            </div>
                            <div class="col-md-3" data-funding-field="restaurant-share">
                                <label class="form-label">Restaurant Share %</label>
                                <input class="form-control" type="number" step="0.01" min="0" max="100" name="restaurant_share_percent" value="{{ old('restaurant_share_percent', $promotion->restaurant_share_percent) }}" data-restaurant-share>
                            </div>
                            <div class="col-md-3" data-budget-field="daily">
                                <label class="form-label">Daily Budget</label>
                                <input class="form-control" type="number" step="0.01" min="0" name="daily_budget" value="{{ old('daily_budget', $promotion->daily_budget) }}" data-daily-budget>
                            </div>
                            <div class="col-md-3" data-budget-field="restaurant">
                                <label class="form-label">Per Restaurant Budget</label>
                                <input class="form-control" type="number" step="0.01" min="0" name="per_restaurant_budget" value="{{ old('per_restaurant_budget', $promotion->per_restaurant_budget) }}" data-per-restaurant-budget>
                            </div>
                            <div class="col-md-6" data-funding-field="partner">
                                <label class="form-label">Partner Name <span class="required-dot d-none" data-partner-required-dot>*</span></label>
                                <input class="form-control" name="partner_name" value="{{ old('partner_name', $promotion->partner_name) }}" data-partner-name>
                                <div class="form-text">Required only for bank/partner funded offers.</div>
                            </div>
                            <div class="col-md-6">
                                @php
                                    $campaignBannerUrl = $promotionImageUrls['campaign_banner'] ?? null;
                                    $campaignTileClass = 'upload-tile'.($campaignBannerUrl ? ' has-image' : '');
                                @endphp
                                <label class="form-label">Campaign Banner</label>
                                <label class="{{ $campaignTileClass }}" data-upload-tile>
                                    <input class="d-none" type="file" name="campaign_banner" accept="image/*" data-image-input>
                                    <img src="{{ $campaignBannerUrl ?: '' }}" alt="Campaign banner preview" data-image-preview>
                                    <span class="upload-placeholder"><i class="fas fa-image d-block mb-2"></i>Upload campaign banner</span>
                                </label>
                            </div>
                            <div class="col-12">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_exclusive" value="1" {{ $checked((bool) old('is_exclusive', $promotion->is_exclusive)) }}>
                                            <label class="form-check-label fw-bold">Exclusive</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="allow_multiple" value="1" {{ $checked((bool) old('allow_multiple', $stacking['allow_multiple'] ?? false)) }}>
                                            <label class="form-check-label fw-bold">Stackable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3" data-coupon-stacking>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="allow_coupon_promotion" value="1" {{ $checked((bool) old('allow_coupon_promotion', $stacking['allow_coupon_promotion'] ?? true)) }}>
                                            <label class="form-check-label fw-bold">Coupon + Promotion</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="best_offer_only" value="1" {{ $checked((bool) old('best_offer_only', $stacking['best_offer_only'] ?? false)) }}>
                                            <label class="form-check-label fw-bold">Best Offer Only</label>
                                        </div>
                                    </div>
                                    @php($fraudRules = (array) ($promotion->fraud_rules ?? []))
                                    @foreach(['fraud_first_order_only' => ['first_order_only', 'First Order Only'], 'fraud_one_per_user' => ['one_per_user', 'One Per User'], 'fraud_one_per_device' => ['one_per_device', 'One Per Device'], 'fraud_one_per_address' => ['one_per_address', 'One Per Address'], 'fraud_one_per_payment_instrument' => ['one_per_payment_instrument', 'One Per Payment']] as $field => [$rule, $label])
                                        <div class="col-md-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" {{ $checked((bool) old($field, $fraudRules[$rule] ?? false)) }}>
                                                <label class="form-check-label fw-bold">{{ $label }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Internal Notes</label>
                                <textarea class="form-control" name="internal_notes" rows="3" maxlength="200">{{ old('internal_notes', data_get($visibility, 'internal_notes')) }}</textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="form-card" data-wizard-panel="7">
                    <div class="form-card-header">
                        <h5 class="section-title">Review Promotion</h5>
                        <p class="section-subtitle">Check the campaign setup before saving.</p>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="summary-row"><span>Title</span><span data-summary-review="title">{{ old('title', $promotion->title ?: 'Untitled') }}</span></div>
                                <div class="summary-row"><span>Owner</span><span data-summary-review="owner">{{ ucfirst($currentOwner) }}</span></div>
                                <div class="summary-row"><span>Restaurant</span><span data-summary-review="restaurant">Entire platform</span></div>
                                <div class="summary-row"><span>Status</span><span data-summary-review="status">{{ ucfirst(old('status', $promotion->status ?: 'draft')) }}</span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="summary-row"><span>Promotion Type</span><span data-summary-review="promotion_type">{{ ucwords(str_replace('_', ' ', $currentPromotionType)) }}</span></div>
                                <div class="summary-row"><span>Reward</span><span data-summary-review="reward">{{ $rewardTypes[$currentRewardType] ?? ucwords(str_replace('_', ' ', $currentRewardType)) }}</span></div>
                                <div class="summary-row"><span>Value</span><span data-summary-review="value">{{ old('reward_value', $reward['value'] ?? 0) }}</span></div>
                                <div class="summary-row"><span>Priority</span><span data-summary-review="priority">{{ old('priority', $promotion->priority ?: 100) }}</span></div>
                                <div class="summary-row"><span>Funding</span><span data-summary-review="funding">{{ ucwords(str_replace('_', ' ', old('funding_type', $promotion->funding_type ?: $promotion->resolvedFundingType()))) }}</span></div>
                                <div class="summary-row"><span>Budget</span><span data-summary-review="budget">{{ old('campaign_tab_budget', $promotion->total_budget ?? data_get($visibility, 'campaign_budget', 'No limit')) }}</span></div>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-circle-info me-2"></i>Only eligible users will see tags/offers according to promotion audience and conditions.
                        </div>
                    </div>
                </section>

                <div class="wizard-actions">
                    <button class="btn btn-outline-secondary" type="button" data-wizard-prev>
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </button>
                    <button class="btn btn-primary" type="button" data-wizard-next>
                        Continue<i class="fas fa-arrow-right ms-2"></i>
                    </button>
                    <button class="btn btn-primary d-none" type="submit" data-wizard-save>
                        <i class="fas fa-save me-2"></i>Save Promotion
                    </button>
                </div>
            </main>

            <aside class="summary-card">
                <div class="form-card">
                    <div class="form-card-header">
                        <h5 class="section-title">Summary</h5>
                    </div>
                    <div class="form-card-body">
                        <div class="summary-row"><span>Title</span><span data-summary="title">{{ old('title', $promotion->title ?: 'Untitled') }}</span></div>
                        <div class="summary-row"><span>Owner</span><span data-summary="owner">{{ ucfirst($currentOwner) }}</span></div>
                        <div class="summary-row"><span>Restaurant</span><span data-summary="restaurant">Entire platform</span></div>
                        <div class="summary-row"><span>Type</span><span data-summary="promotion_type">{{ ucwords(str_replace('_', ' ', $currentPromotionType)) }}</span></div>
                        <div class="summary-row"><span>Reward</span><span data-summary="reward">{{ $rewardTypes[$currentRewardType] ?? ucwords(str_replace('_', ' ', $currentRewardType)) }}</span></div>
                        <div class="summary-row"><span>Status</span><span data-summary="status">{{ ucfirst(old('status', $promotion->status ?: 'draft')) }}</span></div>
                        <div class="summary-row"><span>Value</span><span data-summary="value">{{ old('reward_value', $reward['value'] ?? 0) }}</span></div>
                        <div class="summary-row"><span>Priority</span><span data-summary="priority">{{ old('priority', $promotion->priority ?: 100) }}</span></div>
                        <div class="summary-row"><span>Funding</span><span data-summary="funding">{{ ucwords(str_replace('_', ' ', old('funding_type', $promotion->funding_type ?: $promotion->resolvedFundingType()))) }}</span></div>
                        <div class="summary-row"><span>Budget</span><span data-summary="budget">{{ old('campaign_tab_budget', $promotion->total_budget ?? data_get($visibility, 'campaign_budget', 'No limit')) }}</span></div>
                        <div class="small text-muted mt-3">Save as draft while configuring media, targeting, and reward pool.</div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-card-body">
                        <button class="btn btn-primary w-100 mb-2" type="submit" data-side-submit>
                            <i class="fas fa-save me-2"></i>Save Promotion
                        </button>
                        <a href="{{ route('admin.promotion-engine.index') }}" class="btn btn-outline-secondary w-100">Cancel</a>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-promotion-form]');
    if (!form) return;

    const owner = form.querySelector('[data-owner]');
    const restaurant = form.querySelector('[data-restaurant]');
    const promotionType = form.querySelector('[data-promotion-type]');
    const rewardType = form.querySelector('[data-reward-type]');
    const itemSelect = form.querySelector('[data-item-select]');
    const categorySelect = form.querySelector('[data-category-select]');
    const freeItemSelect = form.querySelector('[data-free-item-select]');
    const menuFilterCuisineSelect = form.querySelector('[data-menu-filter-cuisine]');
    const menuFilterCategorySelect = form.querySelector('[data-menu-filter-category]');
    const menuFilterSubcategorySelect = form.querySelector('[data-menu-filter-subcategory]');
    const clearMenuFiltersButton = form.querySelector('[data-clear-menu-filters]');
    const menuFilterHelp = form.querySelector('[data-menu-filter-help]');
    const restaurantHelp = form.querySelector('[data-restaurant-help]');
    const comboGroupsContainer = form.querySelector('[data-combo-groups]');
    const addComboGroupButton = form.querySelector('[data-add-combo-group]');
    const targetCuisineSelect = form.querySelector('[data-target-cuisine-select]');
    const targetSubcategorySelect = form.querySelector('[data-target-subcategory-select]');
    const conditionCategorySelect = form.querySelector('[data-condition-category-select]');
    const excludeCategorySelect = form.querySelector('[data-exclude-category-select]');
    const conditionItemSelect = form.querySelector('[data-condition-item-select]');
    const excludeItemSelect = form.querySelector('[data-exclude-item-select]');
    const typeHelp = form.querySelector('[data-type-help]');
    const modeHelp = form.querySelector('[data-mode-help]');
    const valueHelp = form.querySelector('[data-value-help]');
    const couponSection = form.querySelector('[data-coupon-section]');
    const couponStacking = form.querySelector('[data-coupon-stacking]');
    const scratchSection = form.querySelector('[data-scratch-section]');
    const wizardTabs = Array.from(form.querySelectorAll('[data-wizard-tab]'));
    const wizardPanels = Array.from(form.querySelectorAll('[data-wizard-panel]'));
    const wizardPrev = form.querySelector('[data-wizard-prev]');
    const wizardNext = form.querySelector('[data-wizard-next]');
    const wizardSave = form.querySelector('[data-wizard-save]');
    const wizardProgress = form.querySelector('[data-wizard-progress]');
    const wizardMobileStep = form.querySelector('[data-wizard-mobile-step]');
    const sideSubmit = form.querySelector('[data-side-submit]');
    const rewardValueInput = form.querySelector('[name="reward_value"]');
    const fundingType = form.querySelector('[data-funding-type]');
    const fundingHelp = form.querySelector('[data-funding-help]');
    const platformShare = form.querySelector('[data-platform-share]');
    const restaurantShare = form.querySelector('[data-restaurant-share]');
    const partnerName = form.querySelector('[data-partner-name]');
    const partnerRequiredDots = form.querySelectorAll('[data-partner-required-dot]');
    const totalBudget = form.querySelector('[data-total-budget]');
    const dailyBudget = form.querySelector('[data-daily-budget]');
    const perRestaurantBudget = form.querySelector('[data-per-restaurant-budget]');
    let autoFundingFromOwner = @json(! $promotion->exists && old('funding_type') === null);
    const typeCatalog = @json($typeCatalog);
    const typeBucketByPromotion = @json($typeBucketByPromotion);
    const rewardByPromotion = @json($rewardByPromotion);
    const rewardLabels = @json($rewardTypes);
    let currentStep = 0;

    const rewardFieldMatrix = {
        free_delivery: [],
        delivery_discount: ['value', 'value-mode', 'max-discount'],
        packaging_discount: ['value', 'value-mode', 'max-discount'],
        wallet_cashback: ['value'],
        reward_points: ['value'],
        scratch_card: [],
        gift_voucher: ['value'],
        referral_bonus: ['value', 'referral'],
        festival_offer: ['value', 'max-discount'],
        flash_sale: ['value', 'max-discount'],
        custom_rule: ['custom'],
        percentage_discount: ['value', 'max-discount', 'menu-finder'],
        flat_discount: ['value', 'menu-finder'],
        fixed_price: ['value', 'menu-finder', 'items', 'categories'],
        item_discount: ['value', 'max-discount', 'menu-finder', 'items'],
        category_discount: ['value', 'max-discount', 'menu-finder', 'categories'],
        combo_deal: ['value', 'max-discount', 'menu-finder', 'combo-groups'],
        meal_deal: ['value', 'max-discount', 'menu-finder', 'combo-groups'],
        bogo: ['menu-finder', 'buy-free', 'items', 'categories', 'free-item'],
        buy_x_get_y: ['menu-finder', 'buy-free', 'items', 'categories', 'free-item'],
        buy_2_get_1: ['menu-finder', 'buy-free', 'items', 'categories', 'free-item'],
        buy_3_get_1: ['menu-finder', 'buy-free', 'items', 'categories', 'free-item'],
        buy_3_get_2: ['menu-finder', 'buy-free', 'items', 'categories', 'free-item'],
        free_item: ['menu-finder', 'items', 'categories', 'free-item'],
    };

    const rewardCopy = {
        percentage: 'Percentage value, for example 20.',
        flat: 'Flat discount amount.',
        fixed_price: 'Final fixed payable price.',
        delivery_discount: 'Enter the delivery charge discount using the selected value type.',
        packaging_discount: 'Enter the packaging charge discount using the selected value type.',
        combo_deal: 'Set combo deal price in Value and map at least two paid items.',
        meal_deal: 'Set meal deal price in Value and map at least two paid items.',
        reward_points: 'Enter points in Value.',
        wallet_credit: 'Enter wallet cashback amount.',
        gift_voucher: 'Enter gift voucher value.',
    };

    function ownerBucket() {
        return ['restaurant', 'branch'].includes(owner?.value) ? 'restaurant' : 'admin';
    }

    function labelize(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()) || 'Not set';
    }

    function setVisible(element, visible) {
        if (!element) return;
        element.classList.toggle('d-none', !visible);
        element.querySelectorAll('input, select, textarea').forEach(input => {
            if (input === rewardType) return;
            input.disabled = !visible;
        });
    }

    function selectedMode() {
        return form.querySelector('[data-application-mode]:checked')?.value || 'automatic';
    }

    function availableSteps() {
        return wizardTabs
            .filter(tab => !tab.classList.contains('d-none'))
            .map(tab => Number(tab.dataset.wizardTab));
    }

    function stepLabel(step) {
        const tab = wizardTabs.find(item => Number(item.dataset.wizardTab) === step);
        const title = tab?.querySelector('.wizard-step-title')?.textContent?.trim() || 'Step';
        const index = availableSteps().indexOf(step) + 1;
        const total = availableSteps().length;
        return `Step ${index} of ${total}: ${title}`;
    }

    function panelForStep(step) {
        return wizardPanels.find(panel => Number(panel.dataset.wizardPanel) === step);
    }

    function stepForElement(element) {
        const panel = element?.closest('[data-wizard-panel]');
        return panel ? Number(panel.dataset.wizardPanel) : currentStep;
    }

    function firstInvalidControl() {
        return Array.from(form.querySelectorAll('input, select, textarea'))
            .find(input => !input.disabled && !input.checkValidity());
    }

    function showFirstInvalidControl() {
        const input = firstInvalidControl();
        if (!input) return true;
        goToStep(stepForElement(input));
        window.setTimeout(() => input.reportValidity(), 80);
        return false;
    }

    function validateStep(step) {
        const panel = panelForStep(step);
        if (!panel || panel.classList.contains('d-none')) return true;
        const controls = Array.from(panel.querySelectorAll('input, select, textarea'))
            .filter(input => !input.disabled && input.offsetParent !== null);
        for (const input of controls) {
            if (!input.checkValidity()) {
                input.reportValidity();
                return false;
            }
        }
        return true;
    }

    function goToStep(step, options = {}) {
        const steps = availableSteps();
        if (!steps.includes(step)) {
            step = steps[0] || 0;
        }
        if (options.validate && !validateStep(currentStep)) return;
        currentStep = step;
        const activeIndex = steps.indexOf(currentStep);

        wizardPanels.forEach(panel => {
            panel.classList.toggle('is-active', Number(panel.dataset.wizardPanel) === currentStep);
        });
        wizardTabs.forEach(tab => {
            const tabStep = Number(tab.dataset.wizardTab);
            const tabIndex = steps.indexOf(tabStep);
            tab.classList.toggle('is-active', tabStep === currentStep);
            tab.classList.toggle('is-complete', tabIndex >= 0 && tabIndex < activeIndex);
        });

        if (wizardPrev) wizardPrev.disabled = activeIndex <= 0;
        if (wizardNext) wizardNext.classList.toggle('d-none', activeIndex >= steps.length - 1);
        if (wizardSave) wizardSave.classList.toggle('d-none', activeIndex < steps.length - 1);
        if (sideSubmit) sideSubmit.classList.toggle('d-none', activeIndex < steps.length - 1);
        if (wizardProgress) wizardProgress.style.width = steps.length > 1 ? `${(activeIndex / (steps.length - 1)) * 100}%` : '100%';
        if (wizardMobileStep) wizardMobileStep.textContent = stepLabel(currentStep);
        panelForStep(currentStep)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function goRelative(direction) {
        const steps = availableSteps();
        const index = steps.indexOf(currentStep);
        const target = steps[Math.min(Math.max(index + direction, 0), steps.length - 1)];
        goToStep(target, { validate: direction > 0 });
    }

    function syncPromotionTypeOptions(forceOwnerCompatible = false) {
        const current = promotionType.value;
        const bucket = ownerBucket();
        const bucketCatalog = typeCatalog[bucket] || {};
        let selectedKey = current;
        if (forceOwnerCompatible && !Object.prototype.hasOwnProperty.call(bucketCatalog, selectedKey)) {
            selectedKey = Object.keys(bucketCatalog)[0] || selectedKey;
        }
        const groupLabels = {
            admin: 'Admin / Platform Offers',
            restaurant: 'Restaurant / Menu Offers',
        };
        promotionType.innerHTML = ['admin', 'restaurant'].map(group => {
            const catalog = typeCatalog[group] || {};
            const options = Object.keys(catalog).map(key => {
                const label = catalog[key]?.label || labelize(key);
                return `<option value="${key}">${label}</option>`;
            }).join('');
            return `<optgroup label="${groupLabels[group]}">${options}</optgroup>`;
        }).join('');
        promotionType.value = selectedKey;
        syncRewardFromType();
    }

    function syncOwnerFromPromotionType() {
        const bucket = typeBucketByPromotion[promotionType.value];
        if (!bucket) return;
        const nextOwner = bucket === 'restaurant' ? 'restaurant' : 'admin';
        if (owner && owner.value !== nextOwner) {
            owner.value = nextOwner;
        }
        if (restaurant) {
            restaurant.required = nextOwner === 'restaurant';
        }
        form.querySelectorAll('[data-restaurant-required-dot]').forEach(dot => {
            dot.classList.toggle('d-none', nextOwner !== 'restaurant');
        });
    }

    function currentTypeCatalog() {
        const bucket = typeBucketByPromotion[promotionType.value] || ownerBucket();
        return typeCatalog[bucket] || {};
    }

    function currentTypeBucketLabel() {
        return (typeBucketByPromotion[promotionType.value] || ownerBucket()) === 'restaurant'
            ? 'restaurant menu'
            : 'admin platform';
    }

    function syncRewardFromType() {
        const type = promotionType.value;
        rewardType.value = rewardByPromotion[type] || 'percentage';
        const meta = currentTypeCatalog()[type] || {};
        const bucket = currentTypeBucketLabel();
        const count = Object.keys(typeCatalog[typeBucketByPromotion[type] || ownerBucket()] || {}).length;
        typeHelp.textContent = meta.label ? `${meta.label} workflow selected. Showing ${count} ${bucket} promotion types.` : 'Related reward settings are selected automatically.';
        updateRewardFields();
        updateSummary();
    }

    function updateOwner() {
        const scoped = ['restaurant', 'branch'].includes(owner.value);
        restaurant.required = scoped;
        form.querySelectorAll('[data-restaurant-required-dot]').forEach(dot => {
            dot.classList.toggle('d-none', !scoped);
        });
        syncPromotionTypeOptions(true);
        updateScopedOptions();
        syncFundingSettings();
    }

    function updateMode() {
        const couponMode = selectedMode() === 'coupon';
        setVisible(couponSection, couponMode);
        setVisible(couponStacking, couponMode || form.querySelector('[name="allow_multiple"]')?.checked);
        modeHelp.textContent = couponMode ? 'Customer must enter or select a coupon code.' : 'Promotion applies automatically when eligibility matches.';
    }

    function defaultFundingForOwner() {
        return ['restaurant', 'branch'].includes(owner?.value) ? 'restaurant' : 'platform';
    }

    function validateSharedShares() {
        if (!platformShare || !restaurantShare) return;
        platformShare.setCustomValidity('');
        restaurantShare.setCustomValidity('');
        if (fundingType?.value !== 'shared') return;

        const platform = Number(platformShare.value || 0);
        const restaurantValue = Number(restaurantShare.value || 0);
        if (Math.abs(platform + restaurantValue - 100) > 0.01) {
            restaurantShare.setCustomValidity('Platform share and restaurant share must total 100%.');
        }
    }

    function syncFundingSettings() {
        if (!fundingType) return;
        if (autoFundingFromOwner) {
            fundingType.value = defaultFundingForOwner();
        }

        const funding = fundingType.value || defaultFundingForOwner();
        const shared = funding === 'shared';
        const bankPartner = funding === 'bank_partner';
        form.querySelectorAll('[data-funding-field="platform-share"], [data-funding-field="restaurant-share"]').forEach(section => setVisible(section, shared));
        setVisible(form.querySelector('[data-funding-field="partner"]'), bankPartner);

        [platformShare, restaurantShare].forEach(input => {
            if (!input) return;
            input.required = shared;
            input.disabled = !shared;
        });
        if (shared && !platformShare.value && !restaurantShare.value) {
            platformShare.value = '50';
            restaurantShare.value = '50';
        }

        if (partnerName) {
            partnerName.required = bankPartner;
            partnerName.disabled = !bankPartner;
        }
        partnerRequiredDots.forEach(dot => dot.classList.toggle('d-none', !bankPartner));

        const help = {
            platform: 'Platform pays the discount. Restaurant payout is not reduced.',
            restaurant: 'Restaurant pays the discount. Liability reduces restaurant payout.',
            shared: 'Platform and restaurant split the discount by the percentages below.',
            bank_partner: 'Bank/partner pays the discount. Partner name is used for settlement reports.',
        };
        if (fundingHelp) fundingHelp.textContent = help[funding] || '';

        validateSharedShares();
        updateSummary();
    }

    function formatBudgetSummary() {
        const parts = [];
        if (totalBudget?.value) parts.push(`Total ${totalBudget.value}`);
        if (dailyBudget?.value) parts.push(`Daily ${dailyBudget.value}`);
        if (perRestaurantBudget?.value) parts.push(`Restaurant ${perRestaurantBudget.value}`);
        return parts.length ? parts.join(' / ') : 'No limit';
    }

    function updateRewardFields() {
        const type = rewardType.value;
        const promotion = promotionType.value;
        const visibleFields = rewardFieldMatrix[promotion] || rewardFieldMatrix[type] || [];

        form.querySelectorAll('[data-reward-field]').forEach(section => {
            const key = section.dataset.rewardField;
            setVisible(section, visibleFields.includes(key));
        });

        setVisible(scratchSection, type === 'scratch_card');
        form.querySelectorAll('[data-scratch-tab]').forEach(tab => {
            tab.classList.toggle('d-none', type !== 'scratch_card');
        });
        if (type !== 'scratch_card' && currentStep === 3) {
            goToStep(4);
        }
        valueHelp.textContent = rewardCopy[type] || '';
        form.querySelector('[data-summary="reward"]').textContent = rewardLabels[type] || labelize(type);
        form.querySelectorAll('[data-summary-review="reward"]').forEach(node => {
            node.textContent = rewardLabels[type] || labelize(type);
        });
    }

    function updateScopedOptions() {
        const menuFilters = {
            cuisineIds: selectedValues(menuFilterCuisineSelect),
            categoryIds: selectedValues(menuFilterCategorySelect),
            subcategoryIds: selectedValues(menuFilterSubcategorySelect),
        };
        updateRestaurantOptions(menuFilters);

        const restaurantId = restaurant.value || '';
        const audienceFilters = {
            cuisineIds: selectedValues(targetCuisineSelect),
            categoryIds: selectedValues(conditionCategorySelect),
            subcategoryIds: selectedValues(targetSubcategorySelect),
        };

        [conditionCategorySelect, excludeCategorySelect].forEach(select => {
            if (!select) return;
            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                const optionRestaurantId = option.dataset.restaurantId || '';
                const visible = !restaurantId || !optionRestaurantId || optionRestaurantId === restaurantId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible) option.selected = false;
            });
        });

        const comboGroupItemSelects = Array.from(form.querySelectorAll('[data-combo-group-item-select]'));
        [itemSelect, freeItemSelect, ...comboGroupItemSelects].forEach(select => {
            if (!select) return;
            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                const visible = optionMatchesRestaurant(option, restaurantId) && optionMatchesTaxonomy(option, menuFilters);
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible) option.selected = false;
            });
        });

        [conditionItemSelect, excludeItemSelect].forEach(select => {
            if (!select) return;
            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                const visible = optionMatchesRestaurant(option, restaurantId) && optionMatchesTaxonomy(option, audienceFilters);
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible) option.selected = false;
            });
        });

        updateFilterHelp(menuFilters);
    }

    function selectedValues(select) {
        if (!select) return [];
        return Array.from(select.selectedOptions).map(option => option.value).filter(Boolean);
    }

    function hasTaxonomyFilter(filters) {
        return filters.cuisineIds.length > 0 || filters.categoryIds.length > 0 || filters.subcategoryIds.length > 0;
    }

    function optionMatchesRestaurant(option, restaurantId) {
        const optionRestaurantId = option.dataset.restaurantId || '';
        return !restaurantId || !optionRestaurantId || optionRestaurantId === restaurantId;
    }

    function optionMatchesTaxonomy(option, filters) {
        const cuisineMatches = filters.cuisineIds.length === 0 || filters.cuisineIds.includes(option.dataset.cuisineId || '');
        const categoryMatches = filters.categoryIds.length === 0 || filters.categoryIds.includes(option.dataset.categoryId || '');
        const subcategoryMatches = filters.subcategoryIds.length === 0 || filters.subcategoryIds.includes(option.dataset.subcategoryId || '');
        return cuisineMatches && categoryMatches && subcategoryMatches;
    }

    function updateRestaurantOptions(filters) {
        if (!restaurant) return;
        const hasFilters = hasTaxonomyFilter(filters);
        const matchingRestaurantIds = new Set();
        const sourceOptions = Array.from(itemSelect?.options || []);
        sourceOptions.forEach(option => {
            if (!option.value || !option.dataset.restaurantId) return;
            if (!hasFilters || optionMatchesTaxonomy(option, filters)) {
                matchingRestaurantIds.add(option.dataset.restaurantId);
            }
        });

        Array.from(restaurant.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }
            const visible = !hasFilters || matchingRestaurantIds.has(option.value);
            option.hidden = !visible;
            option.disabled = !visible;
        });

        if (restaurant.value && restaurant.selectedOptions[0]?.disabled) {
            restaurant.value = '';
        }
    }

    function updateFilterHelp(filters) {
        if (!menuFilterHelp && !restaurantHelp) return;
        const hasFilters = hasTaxonomyFilter(filters);
        const restaurantCount = restaurant
            ? Array.from(restaurant.options).filter(option => option.value && !option.disabled).length
            : 0;
        const restaurantId = restaurant?.value || '';
        const itemCount = [itemSelect, freeItemSelect]
            .filter(Boolean)
            .reduce((count, select) => count + Array.from(select.options).filter(option => option.value && !option.disabled && optionMatchesRestaurant(option, restaurantId)).length, 0);
        const text = hasFilters
            ? `${restaurantCount} matching restaurants, ${itemCount} matching menu entries. Select a restaurant to narrow further.`
            : 'Showing all restaurants and menu items.';
        if (menuFilterHelp) menuFilterHelp.textContent = text;
        if (restaurantHelp) restaurantHelp.textContent = hasFilters ? `${restaurantCount} restaurants match selected cuisine/category/subcategory.` : 'Select cuisine/category/subcategory first to narrow restaurants.';
    }

    function updateSummary() {
        const title = form.querySelector('[name="title"]')?.value || 'Untitled';
        const status = form.querySelector('[name="status"]')?.value || 'draft';
        const priority = form.querySelector('[name="priority"]')?.value || '100';
        const value = form.querySelector('[name="reward_value"]')?.value || '0';
        const restaurantText = restaurant?.selectedOptions?.[0]?.textContent?.trim() || 'Entire platform';
        const fundingText = fundingType?.selectedOptions?.[0]?.textContent?.trim() || labelize(defaultFundingForOwner());
        const budgetText = formatBudgetSummary();
        form.querySelector('[data-summary="title"]').textContent = title;
        form.querySelector('[data-summary="owner"]').textContent = labelize(owner?.value);
        form.querySelector('[data-summary="restaurant"]').textContent = restaurantText;
        form.querySelector('[data-summary="promotion_type"]').textContent = labelize(promotionType?.value);
        form.querySelector('[data-summary="status"]').textContent = labelize(status);
        form.querySelector('[data-summary="priority"]').textContent = priority;
        form.querySelector('[data-summary="value"]').textContent = value;
        form.querySelector('[data-summary="funding"]').textContent = fundingText;
        form.querySelector('[data-summary="budget"]').textContent = budgetText;
        form.querySelectorAll('[data-summary-review="title"]').forEach(node => node.textContent = title);
        form.querySelectorAll('[data-summary-review="owner"]').forEach(node => node.textContent = labelize(owner?.value));
        form.querySelectorAll('[data-summary-review="restaurant"]').forEach(node => node.textContent = restaurantText);
        form.querySelectorAll('[data-summary-review="promotion_type"]').forEach(node => node.textContent = labelize(promotionType?.value));
        form.querySelectorAll('[data-summary-review="status"]').forEach(node => node.textContent = labelize(status));
        form.querySelectorAll('[data-summary-review="priority"]').forEach(node => node.textContent = priority);
        form.querySelectorAll('[data-summary-review="value"]').forEach(node => node.textContent = value);
        form.querySelectorAll('[data-summary-review="funding"]').forEach(node => node.textContent = fundingText);
        form.querySelectorAll('[data-summary-review="budget"]').forEach(node => node.textContent = budgetText);
    }

    function initializeSearches() {
        form.querySelectorAll('.smart-select').forEach(wrapper => {
            const search = wrapper.querySelector('[data-smart-search]');
            const select = wrapper.querySelector('[data-smart-options]');
            if (!search || !select) return;
            search.addEventListener('input', () => {
                const term = search.value.trim().toLowerCase();
                Array.from(select.options).forEach(option => {
                    if (!option.value || option.disabled) return;
                    option.hidden = term.length > 0 && !option.textContent.toLowerCase().includes(term);
                });
            });
        });
    }

    function initializeComboGroups() {
        if (!comboGroupsContainer || !addComboGroupButton) return;
        let nextIndex = comboGroupsContainer.querySelectorAll('[data-combo-group]').length;
        const optionTemplate = comboGroupsContainer.querySelector('[data-combo-group-item-select]')?.innerHTML || '';
        addComboGroupButton.addEventListener('click', () => {
            const index = nextIndex++;
            const wrapper = document.createElement('div');
            wrapper.className = 'border rounded-3 p-3 bg-light';
            wrapper.dataset.comboGroup = '1';
            wrapper.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Group Name</label>
                        <input class="form-control" name="combo_groups[${index}][name]" value="Combo ${index + 1}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Actual Total</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="combo_groups[${index}][actual_price]" data-combo-actual readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Effective Price</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="combo_groups[${index}][price]" placeholder="Main value" data-combo-price>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Discount %</label>
                        <input class="form-control" type="number" step="0.01" min="0" max="100" name="combo_groups[${index}][discount_percent]" data-combo-percent readonly>
                    </div>
                    <div class="col-md-2 d-flex align-items-end justify-content-md-end">
                        <button class="btn btn-light text-danger" type="button" data-remove-combo-group>
                            <i class="fas fa-trash me-1"></i>Remove
                        </button>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Menu Items</label>
                        <select class="form-select" name="combo_groups[${index}][item_ids][]" multiple size="6" data-combo-group-item-select>${optionTemplate}</select>
                    </div>
                </div>
            `;
            comboGroupsContainer.appendChild(wrapper);
            updateScopedOptions();
            updateComboGroupPricing(wrapper);
        });
        comboGroupsContainer.addEventListener('change', event => {
            if (event.target.matches('[data-combo-group-item-select]')) {
                updateComboGroupPricing(event.target.closest('[data-combo-group]'));
            }
        });
        comboGroupsContainer.addEventListener('input', event => {
            if (event.target.matches('[data-combo-price], [name="reward_value"]')) {
                updateComboGroupPricing(event.target.closest('[data-combo-group]'));
            }
        });
        comboGroupsContainer.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-combo-group]');
            if (!button) return;
            const groups = comboGroupsContainer.querySelectorAll('[data-combo-group]');
            if (groups.length <= 1) {
                const group = button.closest('[data-combo-group]');
                group?.querySelectorAll('input').forEach(input => {
                    input.value = input.name.includes('[name]') ? 'Combo 1' : '';
                });
                group?.querySelectorAll('option').forEach(option => {
                    option.selected = false;
                });
                updateComboGroupPricing(group);
                return;
            }
            button.closest('[data-combo-group]')?.remove();
        });
        form.querySelector('[name="reward_value"]')?.addEventListener('input', () => {
            comboGroupsContainer.querySelectorAll('[data-combo-group]').forEach(updateComboGroupPricing);
        });
        comboGroupsContainer.querySelectorAll('[data-combo-group]').forEach(updateComboGroupPricing);
    }

    function updateComboGroupPricing(group) {
        if (!group) return;
        const actualInput = group.querySelector('[data-combo-actual]');
        const priceInput = group.querySelector('[data-combo-price]');
        const percentInput = group.querySelector('[data-combo-percent]');
        const selectedOptions = Array.from(group.querySelectorAll('[data-combo-group-item-select] option:checked'));
        const actual = selectedOptions.reduce((sum, option) => sum + Number(option.dataset.price || 0), 0);
        const effective = Number(priceInput?.value || rewardValueInput?.value || 0);
        const percent = actual > 0 && effective > 0 ? Math.max(0, ((actual - effective) / actual) * 100) : 0;
        if (actualInput) actualInput.value = actual ? actual.toFixed(2) : '';
        if (percentInput) percentInput.value = percent ? percent.toFixed(2) : '';
    }

    function initializeUploads() {
        form.querySelectorAll('[data-image-input]').forEach(input => {
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                const tile = input.closest('[data-upload-tile]');
                const preview = tile ? tile.querySelector('[data-image-preview]') : null;
                if (!file || !tile || !preview) return;
                preview.src = URL.createObjectURL(file);
                tile.classList.add('has-image');
            });
        });
    }

    function initializeScratchRows() {
        const table = form.querySelector('[data-scratch-table]');
        const add = form.querySelector('[data-add-scratch-row]');
        const total = form.querySelector('[data-probability-total]');
        if (!table || !add || !total) return;
        const tbody = table.querySelector('tbody');
        let nextIndex = tbody.querySelectorAll('[data-scratch-row]').length;
        const typeOptions = ['wallet_cashback', 'reward_points', 'free_delivery', 'flat_discount', 'percentage_discount', 'buy_x_get_y', 'free_item', 'gift_voucher', 'no_reward']
            .map(type => `<option value="${type}">${labelize(type)}</option>`)
            .join('');
        const updateTotal = () => {
            const sum = Array.from(form.querySelectorAll('[data-probability]'))
                .reduce((carry, input) => carry + Number(input.value || 0), 0);
            total.textContent = `Probability total: ${sum.toFixed(2)}%`;
            total.classList.toggle('text-success', Math.abs(sum - 100) <= 0.01);
            total.classList.toggle('text-danger', Math.abs(sum - 100) > 0.01);
        };
        add.addEventListener('click', () => {
            const index = nextIndex++;
            const tr = document.createElement('tr');
            tr.dataset.scratchRow = '1';
            tr.innerHTML = `
                <td><input class="form-control" name="scratch_rewards[${index}][name]"></td>
                <td><select class="form-select" name="scratch_rewards[${index}][type]">${typeOptions}</select></td>
                <td><input class="form-control" type="number" step="0.01" min="0" name="scratch_rewards[${index}][value]" value="0"></td>
                <td><input class="form-control" type="number" step="0.01" min="0" max="100" name="scratch_rewards[${index}][probability]" value="0" data-probability></td>
                <td><input class="form-control" type="number" min="1" name="scratch_rewards[${index}][priority]" value="${index + 1}"></td>
                <td><input class="form-control" type="number" min="1" name="scratch_rewards[${index}][max_redemptions]"></td>
                <td><input class="form-control" type="number" min="1" name="scratch_rewards[${index}][daily_limit]"></td>
                <td><input class="form-control" type="number" step="0.01" min="0" name="scratch_rewards[${index}][budget]"></td>
                <td><input class="form-control" type="number" min="1" max="365" name="scratch_rewards[${index}][expiry_days]"></td>
                <td><input class="form-control" name="scratch_rewards[${index}][metadata]"></td>
                <td><button class="btn btn-light text-danger" type="button" data-remove-scratch-row><i class="fas fa-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
            updateTotal();
        });
        table.addEventListener('input', event => {
            if (event.target && event.target.matches('[data-probability]')) updateTotal();
        });
        table.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-scratch-row]');
            if (!button) return;
            const rows = tbody.querySelectorAll('[data-scratch-row]');
            if (rows.length <= 1) return;
            button.closest('[data-scratch-row]').remove();
            updateTotal();
        });
        updateTotal();
    }

    owner?.addEventListener('change', updateOwner);
    restaurant?.addEventListener('change', () => {
        updateScopedOptions();
        syncFundingSettings();
        updateSummary();
    });
    [targetCuisineSelect, targetSubcategorySelect, conditionCategorySelect].forEach(select => {
        select?.addEventListener('change', updateScopedOptions);
    });
    [menuFilterCuisineSelect, menuFilterCategorySelect, menuFilterSubcategorySelect].forEach(select => {
        select?.addEventListener('change', () => {
            updateScopedOptions();
            updateSummary();
        });
    });
    clearMenuFiltersButton?.addEventListener('click', () => {
        [menuFilterCuisineSelect, menuFilterCategorySelect, menuFilterSubcategorySelect].forEach(select => {
            if (!select) return;
            Array.from(select.options).forEach(option => {
                option.selected = false;
            });
        });
        updateScopedOptions();
        updateSummary();
    });
    promotionType?.addEventListener('change', () => {
        syncOwnerFromPromotionType();
        syncRewardFromType();
        updateScopedOptions();
        syncFundingSettings();
    });
    form.querySelectorAll('[data-application-mode]').forEach(input => input.addEventListener('change', updateMode));
    fundingType?.addEventListener('change', () => {
        autoFundingFromOwner = false;
        syncFundingSettings();
    });
    [platformShare, restaurantShare].forEach(input => input?.addEventListener('input', validateSharedShares));
    [totalBudget, dailyBudget, perRestaurantBudget].forEach(input => input?.addEventListener('input', updateSummary));
    wizardTabs.forEach(tab => tab.addEventListener('click', () => goToStep(Number(tab.dataset.wizardTab), { validate: false })));
    wizardPrev?.addEventListener('click', () => goRelative(-1));
    wizardNext?.addEventListener('click', () => goRelative(1));
    form.addEventListener('invalid', event => {
        const target = event.target;
        if (target && target.matches('input, select, textarea')) {
            goToStep(stepForElement(target));
        }
    }, true);
    form.addEventListener('submit', event => {
        if (!showFirstInvalidControl()) {
            event.preventDefault();
            event.stopPropagation();
        }
    });
    form.querySelector('[name="allow_multiple"]')?.addEventListener('change', updateMode);
    form.querySelectorAll('[data-summary-field]').forEach(input => {
        input.addEventListener('input', updateSummary);
        input.addEventListener('change', updateSummary);
    });

    initializeSearches();
    initializeUploads();
    initializeScratchRows();
    initializeComboGroups();
    updateOwner();
    updateMode();
    syncFundingSettings();
    updateRewardFields();
    updateSummary();
    goToStep(0);
});
</script>
@endsection

