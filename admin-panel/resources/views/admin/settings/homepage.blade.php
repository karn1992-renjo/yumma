@extends('layouts.admin')

@section('title', 'Homepage Content')
@section('header', 'Homepage Content')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-home"></i> Web Experience</span>
            <h1>Homepage Content</h1>
            <p>Manage hero copy, section headings, partner messaging, and footer labels used by the public storefront.</p>
        </div>
        <a href="{{ route('admin.home-sections.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-layer-group me-2"></i>Manage Home Sections
        </a>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Homepage Copy</h2>
                <p class="settings-card-subtitle">HTML is supported for highlighted section titles where existing templates expect it.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.homepage">

                <div class="settings-section-title">Hero Search</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Homepage Hero Title</label>
                        <input type="text" name="hero_title" class="form-control" value="{{ $settings['hero_title'] ?? 'Where do you want to order from?' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Homepage Hero Subtitle</label>
                        <input type="text" name="hero_subtitle" class="form-control" value="{{ $settings['hero_subtitle'] ?? 'Discover the best restaurants in your neighborhood' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Location Input Placeholder</label>
                        <input type="text" name="hero_location_placeholder" class="form-control" value="{{ $settings['hero_location_placeholder'] ?? 'Enter delivery location' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Search Input Placeholder</label>
                        <input type="text" name="hero_search_placeholder" class="form-control" value="{{ $settings['hero_search_placeholder'] ?? 'Search for restaurant, cuisine or dish' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Homepage Search Button Text</label>
                        <input type="text" name="hero_search_button_text" class="form-control" value="{{ $settings['hero_search_button_text'] ?? 'Search' }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Home Sections</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Category Section Title</label>
                        <input type="text" name="category_section_title" class="form-control" value="{{ $settings['category_section_title'] ?? 'Explore <span style=\"color: #FF6B35;\">Categories</span>' }}">
                        <small class="text-muted">HTML tags like <code>&lt;span&gt;</code> are allowed.</small>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Category Section Subtitle</label>
                        <input type="text" name="category_section_subtitle" class="form-control" value="{{ $settings['category_section_subtitle'] ?? 'Discover food by cuisines & categories' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Collections Section Title</label>
                        <input type="text" name="collection_section_title" class="form-control" value="{{ $settings['collection_section_title'] ?? 'Collections' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Collections Section Subtitle</label>
                        <input type="text" name="collection_section_subtitle" class="form-control" value="{{ $settings['collection_section_subtitle'] ?? 'Explore curated lists of top restaurants' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Restaurants Section Title</label>
                        <input type="text" name="restaurants_section_title" class="form-control" value="{{ $settings['restaurants_section_title'] ?? 'Restaurants <span style=\"color: #FF6B35;\">near you</span>' }}">
                        <small class="text-muted">HTML tags like <code>&lt;span&gt;</code> are allowed.</small>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Restaurants Section Subtitle</label>
                        <input type="text" name="restaurants_section_subtitle" class="form-control" value="{{ $settings['restaurants_section_subtitle'] ?? 'Discover the best restaurants in your area' }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Customer App Menu Price Filter</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Bottom Navigation Label</label>
                        <input type="text" name="home_menu_price_filter_label" class="form-control" value="{{ $settings['home_menu_price_filter_label'] ?? 'Filter' }}" placeholder="Filter">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Screen Title</label>
                        <input type="text" name="home_menu_price_filter_title" class="form-control" value="{{ $settings['home_menu_price_filter_title'] ?? 'Items in your budget' }}" placeholder="Items in your budget">
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Screen Subtitle</label>
                        <input type="text" name="home_menu_price_filter_subtitle" class="form-control" value="{{ $settings['home_menu_price_filter_subtitle'] ?? 'Menu items matched from restaurants near you' }}" placeholder="Menu items matched from restaurants near you">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Min Price</label>
                        <input type="number" min="0" step="0.01" name="home_menu_price_filter_min_price" class="form-control" value="{{ $settings['home_menu_price_filter_min_price'] ?? '' }}" placeholder="0">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Max Price</label>
                        <input type="number" min="0" step="0.01" name="home_menu_price_filter_max_price" class="form-control" value="{{ $settings['home_menu_price_filter_max_price'] ?? '250' }}" placeholder="250">
                    </div>
                </div>
                <div class="settings-section-title mt-4">Partner Modal</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Partner Nav Link Text</label>
                        <input type="text" name="partner_nav_text" class="form-control" value="{{ $settings['partner_nav_text'] ?? 'Partner with Us' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Partner Modal Title</label>
                        <input type="text" name="partner_modal_title" class="form-control" value="{{ $settings['partner_modal_title'] ?? 'Partner with ' . ($settings['app_name'] ?? 'FoodFlow') }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Partner Modal Subtitle</label>
                        <input type="text" name="partner_modal_subtitle" class="form-control" value="{{ $settings['partner_modal_subtitle'] ?? 'Choose a partner journey and grow with our delivery network.' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Restaurant Partner Card Title</label>
                        <input type="text" name="partner_restaurant_title" class="form-control" value="{{ $settings['partner_restaurant_title'] ?? 'Restaurant Partner' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Restaurant Partner Card Text</label>
                        <input type="text" name="partner_restaurant_text" class="form-control" value="{{ $settings['partner_restaurant_text'] ?? 'List your restaurant & reach thousands of customers' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Delivery Partner Card Title</label>
                        <input type="text" name="partner_driver_title" class="form-control" value="{{ $settings['partner_driver_title'] ?? 'Delivery Partner' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Delivery Partner Card Text</label>
                        <input type="text" name="partner_driver_text" class="form-control" value="{{ $settings['partner_driver_text'] ?? 'Earn money by delivering on your own schedule' }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Footer Labels</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Footer Description</label>
                        <input type="text" name="footer_description" class="form-control" value="{{ $settings['footer_description'] ?? 'Order food from the best restaurants in your city. Fast delivery, great taste!' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Footer Copyright Text</label>
                        <input type="text" name="footer_copyright" class="form-control" value="{{ $settings['footer_copyright'] ?? 'All rights reserved.' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer Company Title</label>
                        <input type="text" name="footer_company_title" class="form-control" value="{{ $settings['footer_company_title'] ?? 'Company' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer Support Title</label>
                        <input type="text" name="footer_support_title" class="form-control" value="{{ $settings['footer_support_title'] ?? 'Support' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer Legal Title</label>
                        <input type="text" name="footer_legal_title" class="form-control" value="{{ $settings['footer_legal_title'] ?? 'Legal' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer About Link Text</label>
                        <input type="text" name="footer_link_about" class="form-control" value="{{ $settings['footer_link_about'] ?? 'About Us' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer Careers Link Text</label>
                        <input type="text" name="footer_link_careers" class="form-control" value="{{ $settings['footer_link_careers'] ?? 'Careers' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer Blog Link Text</label>
                        <input type="text" name="footer_link_blog" class="form-control" value="{{ $settings['footer_link_blog'] ?? 'Blog' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer Help Link Text</label>
                        <input type="text" name="footer_link_help" class="form-control" value="{{ $settings['footer_link_help'] ?? 'Help Center' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer Contact Link Text</label>
                        <input type="text" name="footer_link_contact" class="form-control" value="{{ $settings['footer_link_contact'] ?? 'Contact Us' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Footer FAQs Link Text</label>
                        <input type="text" name="footer_link_faqs" class="form-control" value="{{ $settings['footer_link_faqs'] ?? 'FAQs' }}">
                    </div>
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Homepage Content</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
