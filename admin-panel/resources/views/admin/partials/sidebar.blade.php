<div class="sidebar" id="sidebar">
    @php
        $appName = App\Models\AppSetting::getValue('app_name', 'FoodFlow');
        $appLogo = App\Models\AppSetting::getValue('app_logo', null);
        $headerBrandingType = App\Models\AppSetting::getValue('header_branding_type', 'text');
        $headerBrandingType = in_array($headerBrandingType, ['text', 'logo', 'logo_text']) ? $headerBrandingType : 'text';
        $appLogoUrl = $appLogo && str_starts_with($appLogo, 'branding/')
            ? route('media.branding', ['file' => basename($appLogo)])
            : ($appLogo ? \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) : null);
    @endphp

    <div class="sidebar-logo-section">
        <div class="sidebar-logo-icon">
            @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogo)
                <img src="{{ $appLogoUrl }}" alt="{{ $appName }}" class="sidebar-logo-image">
            @else
                <i class="fas fa-crown"></i>
            @endif
        </div>
        <div class="sidebar-logo-text">
            <h2>Super<span>Admin</span></h2>
            <small>Control Panel</small>
        </div>
    </div>
    
    <div class="sidebar-nav-wrapper">
        <!-- MAIN SECTION -->
        <div class="sidebar-section-title">MAIN</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>
        
        <!-- MANAGEMENT SECTION -->
        <div class="sidebar-section-title">MANAGEMENT</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item sidebar-parent" id="branch-management-menu">
                <a href="#" onclick="toggleSettingsSubmenu(event)" class="sidebar-nav-link sidebar-parent-link {{ request()->routeIs('admin.branches*') ? 'active open' : '' }}">
                    <i class="fas fa-code-branch"></i>
                    <span>Branch Management</span>
                    <i class="fas fa-chevron-down sidebar-submenu-toggle"></i>
                </a>
                <ul class="sidebar-submenu {{ request()->routeIs('admin.branches*') ? 'open' : '' }}">
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.index') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>All Branches</span></a></li>
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.create') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.create') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>Create Branch</span></a></li>
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.users') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.users') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>Branch Users</span></a></li>
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.wallets') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.wallets') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>Branch Wallets</span></a></li>
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.settlements') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.settlements') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>Branch Settlements</span></a></li>
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.payouts') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.payouts') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>Branch Payouts</span></a></li>
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.reports') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.reports') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>Branch Reports</span></a></li>
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.zones') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.zones') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>Territory Management</span></a></li>
                    <li class="sidebar-nav-item"><a href="{{ route('admin.branches.audit-logs') }}" class="sidebar-nav-link {{ request()->routeIs('admin.branches.audit-logs') ? 'active' : '' }}"><i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i><span>Audit Logs</span></a></li>
                </ul>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.restaurants.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.restaurants.*') ? 'active' : '' }}">
                    <i class="fas fa-store"></i>
                    <span>Restaurants</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.delivery-areas.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.delivery-areas.*') ? 'active' : '' }}">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Delivery Areas</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                    <i class="fas fa-box"></i>
                    <span>Orders</span>
                    @php
                        $pendingOrdersCount = \App\Models\Order::whereIn('status', ['pending', 'confirmed'])->count();
                    @endphp
                    @if($pendingOrdersCount > 0)
                        <span class="sidebar-badge">{{ $pendingOrdersCount > 99 ? '99+' : $pendingOrdersCount }}</span>
                    @endif
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.users.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.drivers.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.drivers.*') ? 'active' : '' }}">
                    <i class="fas fa-truck"></i>
                    <span>Drivers</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.fleet.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('admin.fleet.*') ? 'active' : '' }}">
                    <i class="fas fa-route"></i>
                    <span>Fleet Dashboard</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.partner-applications.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.partner-applications.*') ? 'active' : '' }}">
                    <i class="fas fa-handshake"></i>
                    <span>Partner Applications</span>
                    @php
                        $pendingPartnerApplications = \App\Models\PartnerApplication::where('status', 'pending')->count();
                    @endphp
                    @if($pendingPartnerApplications > 0)
                        <span class="sidebar-badge">{{ $pendingPartnerApplications > 99 ? '99+' : $pendingPartnerApplications }}</span>
                    @endif
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.gigs.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.gigs.*') ? 'active' : '' }}">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Driver Gigs</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.banners.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
                    <i class="fas fa-image"></i>
                    <span>Banners</span>
                </a>
            </li>
        </ul>

        <!-- FOOD MANAGEMENT SECTION -->
        <div class="sidebar-section-title">FOOD MANAGEMENT</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.cuisines.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.cuisines.*') ? 'active' : '' }}">
                    <i class="fas fa-egg"></i>
                    <span>Cuisines</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.global-menu-categories.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.global-menu-categories.*') ? 'active' : '' }}">
                    <i class="fas fa-layer-group"></i>
                    <span>Global Categories</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ url('/admin/master-menu-items') }}" class="sidebar-nav-link {{ request()->routeIs('admin.master-menu-items.index') || request()->routeIs('admin.master-menu-items.edit') ? 'active' : '' }}">
                    <i class="fas fa-list-check"></i>
                    <span>Global Menu Items</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ url('/admin/master-menu-items/create') }}" class="sidebar-nav-link {{ request()->routeIs('admin.master-menu-items.create') ? 'active' : '' }}">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Global Item</span>
                </a>
            </li>
        </ul>
        
        <!-- FINANCE SECTION -->
        <div class="sidebar-section-title">FINANCE</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.payouts.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.payouts.index') ? 'active' : '' }}">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payouts</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.restaurant-approvals.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.restaurant-approvals.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Restaurant Approvals</span>
                    @php
                        $pendingRestaurantApprovals = \App\Models\RestaurantLocationChangeRequest::where('status', 'pending')->count();
                    @endphp
                    @if($pendingRestaurantApprovals > 0)
                        <span class="sidebar-badge">{{ $pendingRestaurantApprovals > 99 ? '99+' : $pendingRestaurantApprovals }}</span>
                    @endif
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.wallets.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.wallets.*') ? 'active' : '' }}">
                    <i class="fas fa-wallet"></i>
                    <span>Wallets</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.gift-cards.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.gift-cards.*') ? 'active' : '' }}">
                    <i class="fas fa-gift"></i>
                    <span>Gift Cards</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.refunds.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.refunds.*') ? 'active' : '' }}">
                    <i class="fas fa-rotate-left"></i>
                    <span>Refunds</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.commissions') }}" class="sidebar-nav-link {{ request()->routeIs('admin.commissions*') ? 'active' : '' }}">
                    <i class="fas fa-percent"></i>
                    <span>Commissions</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.payouts.history') }}" class="sidebar-nav-link {{ request()->routeIs('admin.payouts.history*') ? 'active' : '' }}">
                    <i class="fas fa-history"></i>
                    <span>Payout History</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                    <i class="fas fa-chart-pie"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>
        
        <!-- MARKETING SECTION -->
        <div class="sidebar-section-title">MARKETING</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.campaigns.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.campaigns*') ? 'active' : '' }}">
                    <i class="fas fa-bullhorn"></i>
                    <span>Campaigns</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.push-notifications.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.push-notifications*') ? 'active' : '' }}">
                    <i class="fas fa-paper-plane"></i>
                    <span>Push Notifications</span>
                </a>
            </li>
        </ul>
        
        <!-- DINING SECTION -->
        <div class="sidebar-section-title">DINING</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.dining-bookings.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.dining-bookings.*') ? 'active' : '' }}">
                    <i class="fas fa-utensils"></i>
                    <span>Dining Bookings</span>
                    @php
                        $pendingDiningBookings = \App\Models\DiningBooking::where('status', 'pending')->count();
                    @endphp
                    @if($pendingDiningBookings > 0)
                        <span class="sidebar-badge">{{ $pendingDiningBookings > 99 ? '99+' : $pendingDiningBookings }}</span>
                    @endif
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.celebration-types.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.celebration-types*') ? 'active' : '' }}">
                    <i class="fas fa-glass-cheers"></i>
                    <span>Celebration Types</span>
                </a>
            </li>
        </ul>
        
        <!-- RESTAURANT MANAGEMENT SECTION -->
        <div class="sidebar-section-title">RESTAURANT</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.offline-reasons.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.offline-reasons*') ? 'active' : '' }}">
                    <i class="fas fa-clock"></i>
                    <span>Offline Reasons</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.cancellation-limits') }}" class="sidebar-nav-link {{ request()->routeIs('admin.cancellation-limits*') ? 'active' : '' }}">
                    <i class="fas fa-ban"></i>
                    <span>Cancellation Limits</span>
                </a>
            </li>
        </ul>
        
        <!-- REFUND SECTION -->
        <div class="sidebar-section-title">REFUNDS</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.refund-policies.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.refund-policies.*') ? 'active' : '' }}">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Refund Policies</span>
                </a>
            </li>
        </ul>
        
        <!-- SUPPORT SECTION -->
        <div class="sidebar-section-title">SUPPORT</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('admin.support.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.support.*') ? 'active' : '' }}">
                    <i class="fas fa-headset"></i>
                    <span>Support Tickets</span>
                    @php
                        $openTicketsCount = \App\Models\SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
                    @endphp
                    @if($openTicketsCount > 0)
                        <span class="sidebar-badge" style="background: #dc3545;">{{ $openTicketsCount > 99 ? '99+' : $openTicketsCount }}</span>
                    @endif
                </a>
            </li>
        </ul>
        
        <!-- SYSTEM SECTION WITH SETTINGS SUBMENU -->
        <div class="sidebar-section-title">SYSTEM</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item sidebar-parent" id="settings-menu">
                <a href="#" onclick="toggleSettingsSubmenu(event)" class="sidebar-nav-link sidebar-parent-link {{ request()->routeIs('admin.settings.*') || request()->routeIs('admin.home-sections.*') || request()->routeIs('admin.refund-policies.*') ? 'active open' : '' }}">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                    <i class="fas fa-chevron-down sidebar-submenu-toggle"></i>
                </a>
                <ul class="sidebar-submenu {{ request()->routeIs('admin.settings.*') || request()->routeIs('admin.home-sections.*') || request()->routeIs('admin.refund-policies.*') || request()->routeIs('admin.delivery-charges*') || request()->routeIs('admin.taxes*') ? 'open' : '' }}">
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>General Settings</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.homepage') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.homepage') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Homepage Content</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.home-sections.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.home-sections.*') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Home Sections</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.privacy') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.privacy') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Privacy & Legal</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.driver_assignment') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.driver_assignment') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Driver Assignment</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.communication') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.communication') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Communication</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.notifications') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.notifications') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Notification Settings</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.delivery-charges') }}" class="sidebar-nav-link {{ request()->routeIs('admin.delivery-charges*') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Delivery Charges</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.taxes') }}" class="sidebar-nav-link {{ request()->routeIs('admin.taxes*') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Taxes & Charges</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.branding') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.branding') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Branding</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.payment') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.payment') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Payments</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.payout-settings.edit') }}" class="sidebar-nav-link {{ request()->routeIs('admin.payout-settings.*') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Payout Gateway</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.map') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.map') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Map Settings</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.index') }}#media-storage-settings" data-storage-settings-link class="sidebar-nav-link">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Storage Settings</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.settings.cron') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settings.cron') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Cron Jobs</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="{{ route('admin.refund-policies.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.refund-policies.*') ? 'active' : '' }}">
                            <i class="fas fa-circle" style="font-size: 6px; margin-right: 10px;"></i>
                            <span>Refund Policies</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<style>
    /* Sidebar Submenu Styles */
    .sidebar-parent {
        position: relative;
    }
    
    .sidebar-parent-link {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
    }
    
    .sidebar-submenu-toggle {
        font-size: 12px;
        transition: transform 0.3s ease;
        margin-left: auto;
    }
    
    .sidebar-parent-link.open .sidebar-submenu-toggle {
        transform: rotate(180deg);
    }
    
    .sidebar-submenu {
        list-style: none;
        padding-left: 35px !important;
        margin-top: 5px;
        margin-bottom: 5px;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    
    .sidebar-submenu.open {
        max-height: 9999px;
        overflow: visible;
    }
    
    .sidebar-submenu .sidebar-nav-link {
        padding: 8px 12px !important;
        font-size: 13px;
    }
    
    .sidebar-submenu .sidebar-nav-link i {
        opacity: 0.6;
    }
    
    .sidebar-submenu .sidebar-nav-link.active {
        background: linear-gradient(135deg, rgba(255, 107, 53, 0.1), rgba(255, 107, 53, 0.05));
        color: var(--primary);
    }
    
    /* Support badge styling */
    .sidebar-nav-link .sidebar-badge {
        background: #dc3545;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: auto;
        min-width: 20px;
        text-align: center;
    }
</style>

<script>
    function toggleSettingsSubmenu(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const parentLink = event.currentTarget;
        const submenu = parentLink.nextElementSibling;
        
        // Toggle open class on parent link
        parentLink.classList.toggle('open');
        
        // Toggle open class on submenu
        if (submenu) {
            submenu.classList.toggle('open');
        }
    }
    
    // Keep submenu open if any settings route is active
    document.addEventListener('DOMContentLoaded', function() {
        const settingsRoutes = [
            'admin.settings.index',
            'admin.settings.homepage',
            'admin.home-sections.index',
            'admin.home-sections.create',
            'admin.home-sections.edit',
            'admin.settings.privacy',
            'admin.settings.driver_assignment',
            'admin.settings.communication',
            'admin.settings.notifications',
            'admin.settings.branding',
            'admin.settings.payment',
            'admin.settings.map',
            'admin.settings.cron',
            'admin.payout-settings.edit',
            'admin.delivery-charges',
            'admin.taxes',
            'admin.refund-policies.index'
        ];
        const currentRoute = '{{ request()->route()->getName() }}';
        
        if (settingsRoutes.includes(currentRoute)) {
            const settingsMenu = document.getElementById('settings-menu');
            const parentLink = settingsMenu?.querySelector('.sidebar-parent-link');
            const submenu = settingsMenu?.querySelector('.sidebar-submenu');
            if (parentLink && submenu) {
                parentLink.classList.add('open');
                submenu.classList.add('open');
            }
        }

        const sidebar = document.getElementById('sidebar');
        const storageLink = document.querySelector('[data-storage-settings-link]');
        const scrollKey = 'adminSidebarScrollTop';

        if (sidebar) {
            const savedScroll = Number(sessionStorage.getItem(scrollKey));
            if (Number.isFinite(savedScroll)) {
                sidebar.scrollTop = savedScroll;
            }

            sidebar.addEventListener('scroll', function() {
                sessionStorage.setItem(scrollKey, String(sidebar.scrollTop));
            }, { passive: true });

            sidebar.querySelectorAll('a[href]:not([href="#"])').forEach(function(link) {
                link.addEventListener('click', function() {
                    sessionStorage.setItem(scrollKey, String(sidebar.scrollTop));
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
        
        // Highlight support menu item when on support routes
        const supportRoute = '{{ request()->routeIs('admin.support.*') ? 'active' : '' }}';
        if (supportRoute) {
            const supportLink = document.querySelector('a[href="{{ route("admin.support.index") }}"]');
            if (supportLink) {
                supportLink.classList.add('active');
            }
        }
    });
</script>
