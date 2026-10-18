@php
    $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'FoodFlow'));
    $appLogo = App\Models\AppSetting::getValue('app_logo');
    $headerBrandingType = App\Models\AppSetting::getValue('header_branding_type', 'text');
    $headerBrandingType = in_array($headerBrandingType, ['text', 'logo', 'logo_text'], true) ? $headerBrandingType : 'text';
    $appLogoUrl = $appLogo && str_starts_with($appLogo, 'branding/')
        ? route('media.branding', ['file' => basename($appLogo)])
        : ($appLogo ? \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) : null);

    $isActive = function ($patterns) {
        foreach ((array) $patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };

    $pendingOrdersCount = \App\Models\Order::whereIn('status', ['pending', 'confirmed'])->count();
    $pendingPartnerApplications = \App\Models\PartnerApplication::where('status', 'pending')->count();
    $pendingRestaurantApprovals = \App\Models\RestaurantLocationChangeRequest::where('status', 'pending')->count();
    $pendingDriverRestaurantOnboardings = class_exists(\App\Models\RestaurantOnboarding::class)
        ? \App\Models\RestaurantOnboarding::whereIn('status', ['submitted', 'under_review', 'resubmitted', 'correction_required'])->count()
        : 0;
    $pendingDiningBookings = \App\Models\DiningBooking::where('status', 'pending')->count();
    $openTicketsCount = \App\Models\SupportConversation::where('stage', 'human')->whereIn('status', ['open', 'in_progress'])->count();
    $awaitingFoodReturnCount = \App\Models\Order::where('resale_status', 'expired')->count();

    $badge = fn ($count) => $count > 99 ? '99+' : $count;
    $aiFeatureEnabled = app(\App\Services\Ai\AiSettingsService::class)->bool('ai_enabled');

    // A custom role (support, or any role made via admin.roles.*) only sees a
    // sidebar item once granted the matching permission -- same permission
    // names RolePermissionController gates the actual routes with, so the
    // menu never promises a page the user can't actually open. An item with
    // no 'perm' key at all has no permission representing it yet (its route
    // is still super_admin|admin only, see routes/web.php's "PERMISSION-GATED
    // ADMIN SECTIONS" block), so it stays admin-only here too -- fail closed,
    // not open.
    $isFullAdmin = auth()->user()->hasAnyRole(['super_admin', 'admin']);
    $itemVisible = function ($item) use ($isFullAdmin) {
        if ($isFullAdmin) {
            return true;
        }

        return ! empty($item['perm']) && auth()->user()->hasAnyPermission($item['perm']);
    };
    $sectionVisible = function ($section) use ($itemVisible) {
        return (bool) array_filter($section['items'], $itemVisible);
    };

    $sections = [
        [
            'label' => 'Main',
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'chart-line', 'url' => route('admin.dashboard'), 'active' => ['admin.dashboard'], 'perm' => ['view_dashboard']],
            ],
        ],
        [
            'label' => 'Operations',
            'items' => [
                [
                    'label' => 'Branch Management',
                    'icon' => 'code-branch',
                    'active' => ['admin.branches*'],
                    'perm' => ['manage_branches', 'manage_branch_restaurants', 'manage_branch_drivers', 'manage_branch_zones', 'manage_branch_staff'],
                    'children' => [
                        ['label' => 'All Branches', 'url' => route('admin.branches.index'), 'active' => ['admin.branches.index']],
                        ['label' => 'Create Branch', 'url' => route('admin.branches.create'), 'active' => ['admin.branches.create']],
                        ['label' => 'Branch Users', 'url' => route('admin.branches.users'), 'active' => ['admin.branches.users']],
                        ['label' => 'Branch Wallets', 'url' => route('admin.branches.wallets'), 'active' => ['admin.branches.wallets']],
                        ['label' => 'Settlements', 'url' => route('admin.branches.settlements'), 'active' => ['admin.branches.settlements']],
                        ['label' => 'Payouts', 'url' => route('admin.branches.payouts'), 'active' => ['admin.branches.payouts']],
                        ['label' => 'Reports', 'url' => route('admin.branches.reports'), 'active' => ['admin.branches.reports']],
                        ['label' => 'Territories', 'url' => route('admin.branches.zones'), 'active' => ['admin.branches.zones']],
                        ['label' => 'Audit Logs', 'url' => route('admin.branches.audit-logs'), 'active' => ['admin.branches.audit-logs']],
                    ],
                ],
                [
                    'label' => 'Theatre Management',
                    'icon' => 'film',
                    'active' => ['admin.theatres*'],
                    'perm' => ['view_theatres', 'create_theatres', 'edit_theatres', 'delete_theatres', 'manage_theatres'],
                    'children' => [
                        ['label' => 'All Theatres', 'url' => route('admin.theatres.index'), 'active' => ['admin.theatres.index']],
                        ['label' => 'Create Theatre', 'url' => route('admin.theatres.create'), 'active' => ['admin.theatres.create']],
                    ],
                ],
                ['label' => 'Restaurants', 'icon' => 'store', 'url' => route('admin.restaurants.index'), 'active' => ['admin.restaurants.*'], 'perm' => ['view_restaurants', 'create_restaurants', 'edit_restaurants', 'delete_restaurants']],
                ['label' => 'Delivery Areas', 'icon' => 'map-location-dot', 'url' => route('admin.delivery-areas.index'), 'active' => ['admin.delivery-areas.*'], 'perm' => ['view_delivery_areas', 'create_delivery_areas', 'edit_delivery_areas', 'delete_delivery_areas', 'manage_delivery_areas']],
                ['label' => 'Live Orders', 'icon' => 'bolt', 'url' => route('admin.orders.live'), 'active' => request()->routeIs('admin.orders.live'), 'perm' => ['view_orders', 'create_orders', 'update_order_status', 'cancel_orders', 'view_order_details', 'manage_orders']],
                ['label' => 'Order Queue', 'icon' => 'clock', 'url' => route('admin.orders.index', ['status' => 'action_required']), 'active' => request()->routeIs('admin.orders.index') && request('status') === 'action_required', 'badge' => $pendingOrdersCount ? $badge($pendingOrdersCount) : null, 'badge_id' => 'adminOrderQueueSidebarBadge', 'perm' => ['view_orders', 'create_orders', 'update_order_status', 'cancel_orders', 'view_order_details', 'manage_orders']],
                ['label' => 'Order List', 'icon' => 'box', 'url' => route('admin.orders.index'), 'active' => request()->routeIs('admin.orders.*') && !request()->routeIs('admin.orders.live') && !(request()->routeIs('admin.orders.index') && request('status') === 'action_required'), 'perm' => ['view_orders', 'create_orders', 'update_order_status', 'cancel_orders', 'view_order_details', 'manage_orders']],
                ['label' => 'POS Dashboard', 'icon' => 'cash-register', 'url' => route('admin.pos.index'), 'active' => ['admin.pos.*'], 'perm' => ['view_pos', 'manage_pos']],
                ['label' => 'Users', 'icon' => 'users', 'url' => route('admin.users.index'), 'active' => ['admin.users.*'], 'perm' => ['view_users', 'create_users', 'edit_users', 'delete_users', 'block_users', 'manage_users']],
                ['label' => 'Drivers', 'icon' => 'truck-fast', 'url' => route('admin.drivers.index'), 'active' => ['admin.drivers.*'], 'perm' => ['view_drivers', 'create_drivers', 'edit_drivers', 'delete_drivers', 'assign_drivers', 'manage_drivers']],
                ['label' => 'Restaurant Onboardings', 'icon' => 'store-alt', 'url' => route('admin.restaurant-onboardings.index'), 'active' => ['admin.restaurant-onboardings.*'], 'badge' => $pendingDriverRestaurantOnboardings ? $badge($pendingDriverRestaurantOnboardings) : null, 'perm' => ['view_restaurant_onboardings', 'manage_restaurant_onboardings']],
                [
                    'label' => 'COD Management',
                    'icon' => 'hand-holding-dollar',
                    'active' => ['admin.cod*'],
                    'perm' => ['view_cod', 'manage_cod'],
                    'children' => [
                        ['label' => 'Pending Collection', 'url' => route('admin.cod.index'), 'active' => ['admin.cod.index']],
                        ['label' => 'Reconciliation History', 'url' => route('admin.cod.history'), 'active' => ['admin.cod.history']],
                    ],
                ],
                ['label' => 'Fleet Dashboard', 'icon' => 'route', 'url' => route('admin.fleet.dashboard'), 'active' => ['admin.fleet.*'], 'perm' => ['view_fleet']],

                ['label' => 'Partner Applications', 'icon' => 'handshake', 'url' => route('admin.partner-applications.index'), 'active' => ['admin.partner-applications.*'], 'badge' => $pendingPartnerApplications ? $badge($pendingPartnerApplications) : null, 'perm' => ['view_partner_applications', 'manage_partner_applications']],
            ],
        ],
        ...($aiFeatureEnabled ? [[
            'label' => 'AI Management',
            'items' => [
                [
                    'label' => 'AI Control Center',
                    'icon' => 'brain',
                    'active' => ['admin.ai.*'],
                    'perm' => ['ai.view', 'ai.configure', 'ai.autonomy.change', 'ai.decisions.view', 'ai.actions.execute', 'ai.approvals.manage', 'ai.finance.view', 'ai.accounting.view', 'ai.cost.view', 'ai.kill_switch'],
                    'children' => [
                        ['label' => 'Dashboard', 'url' => route('admin.ai.index'), 'active' => ['admin.ai.index']],
                        ['label' => 'Settings', 'url' => route('admin.ai.settings'), 'active' => ['admin.ai.settings']],
                        ['label' => 'Approvals', 'url' => route('admin.ai.approvals.index'), 'active' => ['admin.ai.approvals.*']],
                        ['label' => 'Decisions', 'url' => route('admin.ai.decisions.index'), 'active' => ['admin.ai.decisions.*']],
                        ['label' => 'Chat', 'url' => route('admin.ai.chat'), 'active' => ['admin.ai.chat']],
                    ],
                ],
            ],
        ]] : []),
        [
            'label' => 'Catalog',
            'items' => [
                ['label' => 'Banners', 'icon' => 'image', 'url' => route('admin.banners.index'), 'active' => ['admin.banners.*'], 'perm' => ['view_banners', 'create_banners', 'edit_banners', 'delete_banners', 'manage_banners']],
                ['label' => 'Cuisines', 'icon' => 'egg', 'url' => route('admin.cuisines.index'), 'active' => ['admin.cuisines.*'], 'perm' => ['view_cuisines', 'create_cuisines', 'edit_cuisines', 'delete_cuisines', 'manage_cuisines']],
                ['label' => 'Global Categories', 'icon' => 'layer-group', 'url' => route('admin.global-menu-categories.index'), 'active' => ['admin.global-menu-categories.*'], 'perm' => ['view_global_categories', 'create_global_categories', 'edit_global_categories', 'delete_global_categories']],
                ['label' => 'Listed Menu', 'icon' => 'utensils', 'url' => route('admin.listed-menu.index'), 'active' => ['admin.listed-menu.*'], 'perm' => ['view_listed_menu', 'create_listed_menu', 'edit_listed_menu', 'delete_listed_menu']],
                ['label' => 'Global Menu Items', 'icon' => 'list-check', 'url' => url('/admin/master-menu-items'), 'active' => ['admin.master-menu-items.index', 'admin.master-menu-items.edit'], 'perm' => ['view_menu_items', 'create_menu_items', 'edit_menu_items', 'delete_menu_items', 'manage_menu']],
                ['label' => 'Add Global Item', 'icon' => 'plus-circle', 'url' => url('/admin/master-menu-items/create'), 'active' => ['admin.master-menu-items.create'], 'perm' => ['view_menu_items', 'create_menu_items', 'edit_menu_items', 'delete_menu_items', 'manage_menu']],
            ],
        ],
        [
            'label' => 'Finance',
            'items' => [
                ['label' => 'Payouts', 'icon' => 'money-bill-wave', 'url' => route('admin.payouts.index'), 'active' => ['admin.payouts.index'], 'perm' => ['view_payouts', 'create_payouts', 'process_payouts', 'approve_payouts', 'manage_payouts']],
                ['label' => 'Restaurant Approvals', 'icon' => 'clipboard-check', 'url' => route('admin.restaurant-approvals.index'), 'active' => ['admin.restaurant-approvals.*'], 'badge' => $pendingRestaurantApprovals ? $badge($pendingRestaurantApprovals) : null, 'perm' => ['view_restaurant_approvals', 'manage_restaurant_approvals']],
                ['label' => 'Wallets', 'icon' => 'wallet', 'url' => route('admin.wallets.index'), 'active' => ['admin.wallets.*'], 'perm' => ['view_payouts', 'create_payouts', 'process_payouts', 'approve_payouts', 'manage_payouts']],
                ['label' => 'Gift Cards', 'icon' => 'gift', 'url' => route('admin.gift-cards.index'), 'active' => ['admin.gift-cards.*'], 'perm' => ['view_payouts', 'create_payouts', 'process_payouts', 'approve_payouts', 'manage_payouts']],
                ['label' => 'Refunds', 'icon' => 'rotate-left', 'url' => route('admin.refunds.index'), 'active' => ['admin.refunds.*'], 'perm' => ['view_refunds', 'manage_refunds']],
                ['label' => 'Payment Recovery', 'icon' => 'triangle-exclamation', 'url' => route('admin.payment-recovery.index'), 'active' => ['admin.payment-recovery.*'], 'perm' => ['view_refunds', 'manage_refunds']],
                ['label' => 'Returns', 'icon' => 'box-open', 'url' => route('admin.returns.index'), 'active' => ['admin.returns.*'], 'perm' => ['view_returns', 'manage_returns']],
                ['label' => 'Awaiting Food Return', 'icon' => 'bolt', 'url' => route('admin.orders.index', ['resale_status' => 'expired']), 'active' => [], 'badge' => $awaitingFoodReturnCount ? $badge($awaitingFoodReturnCount) : null, 'perm' => ['view_orders', 'create_orders', 'update_order_status', 'cancel_orders', 'view_order_details', 'manage_orders']],
                ['label' => 'Commissions', 'icon' => 'percent', 'url' => route('admin.commissions'), 'active' => ['admin.commissions*'], 'perm' => ['manage_commissions']],
                ['label' => 'Subscription Plans', 'icon' => 'layer-group', 'url' => route('admin.subscription-plans.index'), 'active' => ['admin.subscription-plans.*'], 'perm' => ['manage_commissions']],
                ['label' => 'Analytics', 'icon' => 'chart-pie', 'url' => route('admin.analytics'), 'active' => ['admin.analytics', 'admin.reports.*'], 'perm' => ['view_reports', 'export_reports', 'view_sales_report', 'view_earning_report']],
            ],
        ],
        [
            'label' => 'Engagement',
            'items' => [
                [
                    'label' => 'Promotion Engine',
                    'icon' => 'tags',
                    'active' => ['admin.promotion-engine.*'],
                    'perm' => ['view_promos', 'create_promos', 'edit_promos', 'delete_promos', 'manage_promos'],
                    'children' => [
                        ['label' => 'Promotions', 'url' => route('admin.promotion-engine.index'), 'active' => ['admin.promotion-engine.index', 'admin.promotion-engine.create', 'admin.promotion-engine.edit']],
                        ['label' => 'Coupon Library', 'url' => route('admin.promotion-engine.coupons'), 'active' => ['admin.promotion-engine.coupons']],
                        ['label' => 'Scratch Cards', 'url' => route('admin.promotion-engine.scratch-cards'), 'active' => ['admin.promotion-engine.scratch-cards']],
                        ['label' => 'Referral Ledger', 'url' => route('admin.promotion-engine.referrals'), 'active' => ['admin.promotion-engine.referrals']],
                        ['label' => 'Fraud Attempts', 'url' => route('admin.promotion-engine.fraud-attempts'), 'active' => ['admin.promotion-engine.fraud-attempts']],
                        ['label' => 'Bank Partner Settlements', 'url' => route('admin.promotion-engine.bank-partner-settlements'), 'active' => ['admin.promotion-engine.bank-partner-settlements']],
                        ['label' => 'Analytics', 'url' => route('admin.promotion-engine.analytics'), 'active' => ['admin.promotion-engine.analytics']],
                        ['label' => 'Engine Logs', 'url' => route('admin.promotion-engine.logs'), 'active' => ['admin.promotion-engine.logs']],
                    ],
                ],
                [
                    'label' => 'Ad Campaigns',
                    'icon' => 'rectangle-ad',
                    'active' => ['admin.ads-engine.*'],
                    'perm' => ['view_ads', 'manage_ads'],
                    'children' => [
                        ['label' => 'Campaigns', 'url' => route('admin.ads-engine.index'), 'active' => ['admin.ads-engine.index']],
                        ['label' => 'Click Log', 'url' => route('admin.ads-engine.click-log'), 'active' => ['admin.ads-engine.click-log']],
                        ['label' => 'Fraud Signals', 'url' => route('admin.ads-engine.fraud-signals'), 'active' => ['admin.ads-engine.fraud-signals']],
                    ],
                ],
                ['label' => 'Campaigns', 'icon' => 'bullhorn', 'url' => route('admin.campaigns.index'), 'active' => ['admin.campaigns*'], 'perm' => ['manage_campaigns']],
                ['label' => 'Push Notifications', 'icon' => 'paper-plane', 'url' => route('admin.push-notifications.index'), 'active' => ['admin.push-notifications*'], 'perm' => ['manage_push_notifications']],
                ['label' => 'Notification Templates', 'icon' => 'file-lines', 'url' => route('admin.notification-templates.index'), 'active' => ['admin.notification-templates*'], 'perm' => ['manage_notification_templates']],
                ['label' => 'Email Templates', 'icon' => 'envelope-open-text', 'url' => route('admin.email-templates.index'), 'active' => ['admin.email-templates*'], 'perm' => ['manage_email_templates']],
                ['label' => 'Notification Log', 'icon' => 'list-check', 'url' => route('admin.notification-logs.index'), 'active' => ['admin.notification-logs*'], 'perm' => ['view_notification_logs']],
                ['label' => 'Dining Bookings', 'icon' => 'utensils', 'url' => route('admin.dining-bookings.index'), 'active' => ['admin.dining-bookings.*'], 'badge' => $pendingDiningBookings ? $badge($pendingDiningBookings) : null, 'perm' => ['view_dining_bookings', 'manage_dining_bookings']],
                ['label' => 'Celebration Types', 'icon' => 'champagne-glasses', 'url' => route('admin.celebration-types.index'), 'active' => ['admin.celebration-types*'], 'perm' => ['manage_celebration_types']],
                ['label' => 'Customer Reviews', 'icon' => 'star', 'url' => route('admin.reviews.index'), 'active' => ['admin.reviews.*'], 'perm' => ['view_reviews', 'manage_reviews']],
                ['label' => 'Restaurant Stories', 'icon' => 'camera-retro', 'url' => route('admin.restaurant-stories.index'), 'active' => ['admin.restaurant-stories.*'], 'perm' => ['view_reviews', 'manage_reviews']],
                ['label' => 'Support', 'icon' => 'headset', 'url' => route('support.index'), 'active' => ['support.*'], 'badge' => $openTicketsCount ? $badge($openTicketsCount) : null, 'perm' => ['view_support_tickets', 'reply_support_tickets', 'resolve_support_tickets', 'manage_support_tickets']],
                ['label' => 'Call History', 'icon' => 'phone-volume', 'url' => route('admin.call-history.index'), 'active' => ['admin.call-history.*'], 'perm' => ['view_call_history']],
            ],
        ],
        [
            'label' => 'Restaurant Controls',
            'items' => [
                [
                    'label' => 'Driver Gigs',
                    'icon' => 'calendar-alt',
                    'active' => ['admin.gigs.*'],
                    'perm' => ['view_gigs', 'create_gigs', 'edit_gigs', 'delete_gigs', 'book_gigs', 'manage_gigs'],
                    'children' => [
                        ['label' => 'Gig Slots', 'url' => route('admin.gigs.index'), 'active' => ['admin.gigs.index', 'admin.gigs.edit']],
                        ['label' => 'Create Slot', 'url' => route('admin.gigs.create'), 'active' => ['admin.gigs.create']],
                        ['label' => 'Analytics', 'url' => route('admin.gigs.analytics'), 'active' => ['admin.gigs.analytics', 'admin.gigs.operations', 'admin.gigs.heatmap']],
                        ['label' => 'Bulk Create', 'url' => route('admin.gigs.bulk'), 'active' => ['admin.gigs.bulk']],
                        ['label' => 'Payout Approvals', 'url' => route('admin.gigs.payout-approvals'), 'active' => ['admin.gigs.payout-approvals']],
                        ['label' => 'Fraud Signals', 'url' => route('admin.gigs.fraud-signals'), 'active' => ['admin.gigs.fraud-signals']],
                        ['label' => 'Disputes', 'url' => route('admin.gigs.disputes'), 'active' => ['admin.gigs.disputes']],
                    ],
                ],
                ['label' => 'Offline Reasons', 'icon' => 'clock', 'url' => route('admin.offline-reasons.index'), 'active' => ['admin.offline-reasons*'], 'perm' => ['manage_offline_reasons']],
                ['label' => 'Cancellation Limits', 'icon' => 'ban', 'url' => route('admin.cancellation-limits'), 'active' => ['admin.cancellation-limits*'], 'perm' => ['manage_cancellation_limits']],
                ['label' => 'Refund Policies', 'icon' => 'hand-holding-dollar', 'url' => route('admin.refund-policies.index'), 'active' => ['admin.refund-policies.*'], 'perm' => ['manage_refund_policies']],
                ['label' => 'Blog Posts', 'icon' => 'newspaper', 'url' => route('admin.blog.index'), 'active' => ['admin.blog.*'], 'perm' => ['manage_blog_posts']],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                [
                    'label' => 'Settings',
                    'icon' => 'gear',
                    'id' => 'settings-menu',
                    'active' => ['admin.settings.*', 'admin.home-sections.*', 'admin.delivery-charges*', 'admin.taxes*', 'admin.payout-settings.*', 'admin.restaurant-onboardings.settings', 'admin.settings.tax-charges', 'admin.roles.*'],
                    'perm' => ['view_settings', 'update_settings', 'manage_settings', 'manage_app_branding', 'manage_payment_gateways'],
                    'children' => array_values(array_filter([
                        ['label' => 'General Settings', 'url' => route('admin.settings.index'), 'active' => ['admin.settings.index']],
                        ['label' => 'Homepage Content', 'url' => route('admin.settings.homepage'), 'active' => ['admin.settings.homepage']],
                        ['label' => 'SEO', 'url' => route('admin.settings.seo'), 'active' => ['admin.settings.seo']],
                        ['label' => 'Home Sections', 'url' => route('admin.home-sections.index'), 'active' => ['admin.home-sections.*']],
                        ['label' => 'Privacy & Legal', 'url' => route('admin.settings.privacy'), 'active' => ['admin.settings.privacy']],
                        ['label' => 'Driver Assignment', 'url' => route('admin.settings.driver_assignment'), 'active' => ['admin.settings.driver_assignment']],
                        ['label' => 'Communication', 'url' => route('admin.settings.communication'), 'active' => ['admin.settings.communication']],
                        ['label' => 'Call Masking Pool', 'url' => route('admin.call-masking.pool.index'), 'active' => ['admin.call-masking.*']],
                        ['label' => 'Notifications', 'url' => route('admin.settings.notifications'), 'active' => ['admin.settings.notifications']],
                        ['label' => 'Tax & Charges', 'url' => route('admin.settings.tax-charges'), 'active' => ['admin.settings.tax-charges', 'admin.delivery-charges*', 'admin.taxes*']],
                        ['label' => 'Branding', 'url' => route('admin.settings.branding'), 'active' => ['admin.settings.branding']],
                        ['label' => 'App Download QR', 'url' => route('admin.settings.app-download'), 'active' => ['admin.settings.app-download']],
                        ['label' => 'Payments', 'url' => route('admin.settings.payment'), 'active' => ['admin.settings.payment']],
                        ['label' => 'Payout Gateway', 'url' => route('admin.payout-settings.edit'), 'active' => ['admin.payout-settings.*']],
                        ['label' => 'Driver Restaurant Onboarding', 'url' => route('admin.restaurant-onboardings.settings'), 'active' => ['admin.restaurant-onboardings.settings']],
                        ['label' => 'Map Settings', 'url' => route('admin.settings.map'), 'active' => ['admin.settings.map']],
                        ['label' => 'Voice AI', 'url' => route('admin.settings.voice-ai'), 'active' => ['admin.settings.voice-ai']],
                        ['label' => 'Storage Settings', 'url' => route('admin.settings.index') . '#media-storage-settings', 'active' => [], 'attrs' => 'data-storage-settings-link'],
                        ['label' => 'Cron Jobs', 'url' => route('admin.settings.cron'), 'active' => ['admin.settings.cron']],
                        auth()->user()->hasRole('super_admin')
                            ? ['label' => 'Roles & Permissions', 'url' => route('admin.roles.index'), 'active' => ['admin.roles.*']]
                            : null,
                    ])),
                ],
            ],
        ],
    ];
@endphp

<aside class="sidebar admin-sidebar" id="sidebar">
    <div class="sidebar-logo-section admin-sidebar-brand">
        <div class="sidebar-logo-icon">
            @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogoUrl)
                <img src="{{ $appLogoUrl }}" alt="{{ $appName }}" class="sidebar-logo-image">
            @else
                <i class="fas fa-shield-halved"></i>
            @endif
        </div>
        <div class="sidebar-logo-text">
            <h2>{{ $appName }} <span>Admin</span></h2>
            <small>Control Panel</small>
        </div>
    </div>

    <div class="sidebar-search-shell">
        <i class="fas fa-search"></i>
        <input type="search" id="adminSidebarSearch" class="sidebar-search-input" placeholder="Search menu" autocomplete="off">
        <button type="button" id="adminSidebarSearchClear" class="sidebar-search-clear" aria-label="Clear menu search">
            <i class="fas fa-xmark"></i>
        </button>
    </div>

    @php
        $taxCfg = app(\App\Services\Tax\TaxConfig::class);
        if ($taxCfg->gstRegistered() || $taxCfg->tdsRegistered() || $taxCfg->accountingEnabled()) {
            $accItems = [
                ['label' => 'Overview', 'icon' => 'gauge-high', 'url' => route('admin.accounting.overview'), 'active' => ['admin.accounting.overview']],
            ];
            if ($taxCfg->gstRegistered() || $taxCfg->tdsRegistered()) {
                $accItems[] = ['label' => 'GST', 'icon' => 'file-invoice', 'url' => route('admin.accounting.gst'), 'active' => ['admin.accounting.gst']];
                $accItems[] = ['label' => 'TDS', 'icon' => 'hand-holding-dollar', 'url' => route('admin.accounting.tds'), 'active' => ['admin.accounting.tds']];
                $accItems[] = ['label' => 'TCS', 'icon' => 'percent', 'url' => route('admin.accounting.tcs'), 'active' => ['admin.accounting.tcs']];
                $accItems[] = ['label' => 'Settlements', 'icon' => 'scale-balanced', 'url' => route('admin.accounting.settlements'), 'active' => ['admin.accounting.settlements']];
                $accItems[] = ['label' => 'Tax Ledger', 'icon' => 'book', 'url' => route('admin.accounting.ledger'), 'active' => ['admin.accounting.ledger']];
            }
            if ($taxCfg->gigCessEnabled()) {
                $accItems[] = ['label' => 'Cess', 'icon' => 'hand-holding-heart', 'url' => route('admin.accounting.cess'), 'active' => ['admin.accounting.cess']];
            }
            $accItems[] = ['label' => 'Compliance', 'icon' => 'clipboard-list', 'url' => route('admin.accounting.compliance'), 'active' => ['admin.accounting.compliance']];
            $accItems[] = ['label' => 'Balance Sheet', 'icon' => 'scale-unbalanced', 'url' => route('admin.accounting.gl.balance-sheet'), 'active' => ['admin.accounting.gl.balance-sheet']];
            $accItems[] = ['label' => 'Profit & Loss', 'icon' => 'chart-line', 'url' => route('admin.accounting.gl.profit-loss'), 'active' => ['admin.accounting.gl.profit-loss']];
            $accItems[] = ['label' => 'Cash Flow', 'icon' => 'money-bill-transfer', 'url' => route('admin.accounting.gl.cash-flow'), 'active' => ['admin.accounting.gl.cash-flow']];
            $accItems[] = ['label' => 'Trial Balance', 'icon' => 'list-ol', 'url' => route('admin.accounting.gl.trial-balance'), 'active' => ['admin.accounting.gl.trial-balance']];
            $accItems[] = ['label' => 'Journals', 'icon' => 'file-lines', 'url' => route('admin.accounting.gl.journals'), 'active' => ['admin.accounting.gl.journals']];
            $accItems[] = ['label' => 'Chart of Accounts', 'icon' => 'sitemap', 'url' => route('admin.accounting.gl.chart'), 'active' => ['admin.accounting.gl.chart']];
            $accItems[] = ['label' => 'Documents', 'icon' => 'folder-open', 'url' => route('admin.accounting.documents'), 'active' => ['admin.accounting.documents']];
            $accItems = array_map(fn ($item) => $item + ['perm' => ['view_accounting', 'manage_accounting']], $accItems);
            $sections[] = ['label' => 'Business Accounting', 'items' => $accItems];
        }

        $extApps = [];
        if ((string) \App\Models\AppSetting::getValue('integration_accounts_enabled', '0') === '1' && \App\Models\AppSetting::getValue('integration_accounts_url')) {
            $extApps[] = ['label' => 'Accounts App', 'icon' => 'up-right-from-square', 'url' => \App\Models\AppSetting::getValue('integration_accounts_url'), 'active' => [], 'attrs' => 'target="_blank" rel="noopener"'];
        }
        if ((string) \App\Models\AppSetting::getValue('integration_hrms_enabled', '0') === '1' && \App\Models\AppSetting::getValue('integration_hrms_url')) {
            $extApps[] = ['label' => 'HRMS App', 'icon' => 'up-right-from-square', 'url' => \App\Models\AppSetting::getValue('integration_hrms_url'), 'active' => [], 'attrs' => 'target="_blank" rel="noopener"'];
        }
        if ($extApps) {
            $sections[] = ['label' => 'Connected Apps', 'items' => $extApps];
        }
    @endphp

    <nav class="sidebar-nav-wrapper admin-sidebar-scroll" aria-label="Admin navigation">
        @foreach($sections as $section)
            @continue(! $sectionVisible($section))
            <div class="sidebar-section" data-sidebar-section>
                <div class="sidebar-section-title">{{ $section['label'] }}</div>
                <ul class="sidebar-nav">
                @foreach($section['items'] as $item)
                    @continue(! $itemVisible($item))
                    @php
                        $hasChildren = !empty($item['children']);
                        $activeConfig = $item['active'] ?? [];
                        $active = is_bool($activeConfig) ? $activeConfig : $isActive($activeConfig);
                        $itemId = $item['id'] ?? \Illuminate\Support\Str::slug($item['label']) . '-menu';
                    @endphp

                    <li class="sidebar-nav-item {{ $hasChildren ? 'sidebar-parent' : '' }}" id="{{ $hasChildren ? $itemId : '' }}" data-sidebar-item>
                        @if($hasChildren)
                            <a href="#" onclick="toggleSettingsSubmenu(event)" class="sidebar-nav-link sidebar-parent-link {{ $active ? 'active open' : '' }}">
                                <i class="fas fa-{{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                                <i class="fas fa-chevron-down sidebar-submenu-toggle"></i>
                            </a>
                            <ul class="sidebar-submenu {{ $active ? 'open' : '' }}">
                                @foreach($item['children'] as $child)
                                    @php
                                        $childActiveConfig = $child['active'] ?? [];
                                        $childActive = is_bool($childActiveConfig) ? $childActiveConfig : $isActive($childActiveConfig);
                                    @endphp
                                    <li class="sidebar-nav-item" data-sidebar-child>
                                        <a href="{{ $child['url'] }}" {!! $child['attrs'] ?? '' !!} class="sidebar-nav-link {{ $childActive ? 'active' : '' }}">
                                            <i class="fas fa-circle"></i>
                                            <span>{{ $child['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item['url'] }}" {!! $item['attrs'] ?? '' !!} class="sidebar-nav-link {{ $active ? 'active' : '' }}">
                                <i class="fas fa-{{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                                @if(!empty($item['badge']) || !empty($item['badge_id']))
                                    <span class="sidebar-badge {{ empty($item['badge']) ? 'd-none' : '' }}" @if(!empty($item['badge_id'])) id="{{ $item['badge_id'] }}" @endif>{{ $item['badge'] ?? '0' }}</span>
                                @endif
                            </a>
                        @endif
                    </li>
                @endforeach
                </ul>
            </div>
        @endforeach
        <div class="sidebar-empty-state d-none" id="adminSidebarNoResults">No menu items found</div>
    </nav>
</aside>

<style>
    /* Theme-aware: colours come from the layout's --m3-* tokens so the
       sidebar follows light / dark. Layout rules give it the frosted glass. */
    .admin-sidebar {
        background:
            radial-gradient(circle at 10% 0%, color-mix(in srgb, var(--primary) 12%, transparent), transparent 34%),
            var(--m3-nav, #ffffff) !important;
    }

    .admin-sidebar .sidebar-search-shell {
        position: relative;
        padding: 14px 14px 4px;
    }

    .admin-sidebar .sidebar-search-shell .fa-search {
        position: absolute;
        left: 28px;
        top: 50%;
        transform: translateY(-28%);
        color: var(--m3-text-muted);
        font-size: 13px;
        pointer-events: none;
    }

    .admin-sidebar .sidebar-search-input {
        width: 100%;
        height: 42px;
        border: 1px solid var(--m3-outline-strong);
        border-radius: 14px;
        background: var(--m3-tint-input);
        color: var(--m3-text);
        font-size: 13px;
        font-weight: 700;
        outline: none;
        padding: 0 42px 0 38px;
    }

    .admin-sidebar .sidebar-search-input::placeholder {
        color: var(--m3-text-muted);
    }

    .admin-sidebar .sidebar-search-input:focus {
        border-color: color-mix(in srgb, var(--primary) 74%, #ffffff);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent);
    }

    .admin-sidebar .sidebar-search-clear {
        position: absolute;
        right: 21px;
        top: 50%;
        transform: translateY(-28%);
        width: 28px;
        height: 28px;
        border: 0;
        border-radius: 10px;
        background: var(--m3-state);
        color: var(--m3-text-muted);
        display: none;
        align-items: center;
        justify-content: center;
    }

    .admin-sidebar .sidebar-search-clear.show {
        display: inline-flex;
    }

    .admin-sidebar .sidebar-filter-hidden {
        display: none !important;
    }

    .admin-sidebar .sidebar-empty-state {
        margin: 12px 14px;
        padding: 12px;
        border-radius: 14px;
        color: var(--m3-text-muted);
        background: var(--m3-tint-input);
        font-size: 13px;
        font-weight: 700;
        text-align: center;
    }
    .admin-sidebar .admin-sidebar-brand {
        padding: 18px 18px !important;
        min-height: var(--topbar-height);
    }

    .admin-sidebar .sidebar-logo-icon {
        width: 46px !important;
        height: 46px !important;
        border-radius: 16px !important;
    }

    .admin-sidebar .sidebar-logo-text {
        min-width: 0;
    }

    .admin-sidebar .sidebar-logo-text h2 {
        max-width: 185px;
        color: var(--m3-text-strong) !important;
        font-size: 18px !important;
        font-weight: 950 !important;
        line-height: 1.08 !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .admin-sidebar .sidebar-logo-text h2 span {
        color: var(--primary) !important;
    }

    .admin-sidebar .sidebar-logo-text small {
        color: var(--m3-text-muted) !important;
        font-size: 10px !important;
        font-weight: 800 !important;
    }

    .admin-sidebar .admin-sidebar-scroll {
        padding: 12px 10px 18px !important;
        scrollbar-width: thin;
        scrollbar-color: var(--m3-scroll-thumb) transparent;
    }

    .admin-sidebar .admin-sidebar-scroll::-webkit-scrollbar {
        width: 7px;
    }

    .admin-sidebar .admin-sidebar-scroll::-webkit-scrollbar-thumb {
        background: var(--m3-scroll-thumb);
        border-radius: 999px;
    }

    .admin-sidebar .sidebar-section-title {
        margin-top: 10px !important;
        padding: 8px 12px 5px !important;
        color: var(--m3-text-muted) !important;
        font-size: 10px !important;
        font-weight: 950 !important;
        letter-spacing: .14em !important;
    }

    .admin-sidebar .sidebar-nav-item {
        margin-bottom: 3px !important;
    }

    .admin-sidebar .sidebar-nav-link {
        min-height: 40px !important;
        padding: 9px 12px !important;
        border-radius: 13px !important;
        color: var(--m3-text) !important;
        font-size: 13px !important;
        font-weight: 850 !important;
        gap: 10px !important;
        white-space: nowrap;
    }

    .admin-sidebar .sidebar-nav-link span:not(.sidebar-badge) {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .admin-sidebar .sidebar-nav-link i:first-child {
        width: 22px !important;
        height: 22px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
    }

    .admin-sidebar .sidebar-nav-link:hover {
        background: var(--m3-state) !important;
        color: var(--m3-text-strong) !important;
        transform: translateX(2px);
    }

    .admin-sidebar .sidebar-nav-link.active {
        background: var(--m3-secondary-container) !important;
        color: var(--m3-on-container) !important;
        box-shadow: none !important;
    }

    .admin-sidebar .sidebar-parent-link {
        justify-content: flex-start !important;
    }

    .admin-sidebar .sidebar-submenu-toggle {
        margin-left: auto !important;
        font-size: 10px !important;
        transition: transform .2s ease;
    }

    .admin-sidebar .sidebar-parent-link.open .sidebar-submenu-toggle {
        transform: rotate(180deg);
    }

    .admin-sidebar .sidebar-submenu {
        display: block !important;
        max-height: 0;
        margin: 4px 0 5px 24px !important;
        padding-left: 8px !important;
        overflow: hidden;
        border-left: 1px solid var(--m3-outline);
        transition: max-height .25s ease;
    }

    .admin-sidebar .sidebar-submenu.open {
        max-height: 900px;
    }

    .admin-sidebar .sidebar-submenu .sidebar-nav-link {
        min-height: 34px !important;
        padding: 7px 10px !important;
        border-radius: 10px !important;
        color: var(--m3-text-muted) !important;
        font-size: 12px !important;
    }

    .admin-sidebar .sidebar-submenu .sidebar-nav-link i:first-child {
        width: 10px !important;
        height: 10px !important;
        font-size: 5px !important;
        color: var(--m3-text-muted) !important;
        background: transparent !important;
    }

    .admin-sidebar .sidebar-badge {
        margin-left: auto;
        min-width: 24px;
        padding: 2px 7px;
        border-radius: 999px;
        color: #fff;
        background: linear-gradient(135deg, #ef4444, #fb7185) !important;
        font-size: 10px;
        font-weight: 950;
        text-align: center;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('adminSidebarSearch');
        const clearButton = document.getElementById('adminSidebarSearchClear');
        const noResults = document.getElementById('adminSidebarNoResults');
        if (!searchInput) return;

        const normalize = (value) => (value || '').toLowerCase().replace(/\s+/g, ' ').trim();

        function filterSidebar() {
            const query = normalize(searchInput.value);
            let visibleSections = 0;
            clearButton?.classList.toggle('show', query.length > 0);

            document.querySelectorAll('[data-sidebar-section]').forEach((section) => {
                let visibleItems = 0;

                section.querySelectorAll(':scope > .sidebar-nav > [data-sidebar-item]').forEach((item) => {
                    const parentLink = item.querySelector(':scope > .sidebar-nav-link');
                    const submenu = item.querySelector(':scope > .sidebar-submenu');
                    const parentText = normalize(parentLink?.innerText);
                    let childMatch = false;

                    item.querySelectorAll('[data-sidebar-child]').forEach((child) => {
                        const matches = query === '' || normalize(child.innerText).includes(query) || parentText.includes(query);
                        child.classList.toggle('sidebar-filter-hidden', !matches);
                        childMatch = childMatch || matches;
                    });

                    const itemMatch = query === '' || parentText.includes(query) || childMatch;
                    item.classList.toggle('sidebar-filter-hidden', !itemMatch);

                    if (submenu) {
                        const shouldOpen = query !== '' && itemMatch;
                        submenu.classList.toggle('open', shouldOpen || parentLink?.classList.contains('active'));
                        parentLink?.classList.toggle('open', shouldOpen || parentLink.classList.contains('active'));
                    }

                    if (itemMatch) visibleItems++;
                });

                section.classList.toggle('sidebar-filter-hidden', visibleItems === 0);
                if (visibleItems > 0) visibleSections++;
            });

            noResults?.classList.toggle('d-none', visibleSections > 0);
        }

        searchInput.addEventListener('input', filterSidebar);
        clearButton?.addEventListener('click', function () {
            searchInput.value = '';
            searchInput.focus();
            filterSidebar();
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarNav = document.querySelector('.admin-sidebar-scroll');
        const storageLink = document.querySelector('[data-storage-settings-link]');
        const scrollKey = 'adminSidebarScrollTop';

        if (sidebarNav) {
            const savedScroll = Number(sessionStorage.getItem(scrollKey));
            if (Number.isFinite(savedScroll)) {
                sidebarNav.scrollTop = savedScroll;
            }

            sidebarNav.addEventListener('scroll', function() {
                sessionStorage.setItem(scrollKey, String(sidebarNav.scrollTop));
            }, { passive: true });

            sidebarNav.querySelectorAll('a[href]:not([href="#"])').forEach(function(link) {
                link.addEventListener('click', function() {
                    sessionStorage.setItem(scrollKey, String(sidebarNav.scrollTop));
                });
            });
        }

        function syncStorageSettingsLink() {
            if (window.location.hash !== '#media-storage-settings' || !storageLink) return;

            document.querySelectorAll('#settings-menu .sidebar-nav-link.active').forEach(function(link) {
                link.classList.remove('active');
            });
            storageLink.classList.add('active');
        }

        syncStorageSettingsLink();
        window.addEventListener('hashchange', syncStorageSettingsLink);
    });
</script>


