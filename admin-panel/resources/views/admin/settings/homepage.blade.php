@extends('layouts.admin')

@section('title', 'Homepage Content')
@section('header', 'Homepage Content')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Homepage Content</h1>
            <p>Manage homepage hero copy, section titles, partner messaging, and footer labels.</p>
        </div>
        <a href="{{ route('admin.home-sections.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-layer-group me-2"></i>Manage Home Sections
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Homepage Content</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="redirect_to" value="admin.settings.homepage">

                    <div class="row gy-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Homepage Hero Title</label>
                            <input type="text" name="hero_title" class="form-control" value="{{ $settings['hero_title'] ?? 'Where do you want to order from?' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Homepage Hero Subtitle</label>
                            <input type="text" name="hero_subtitle" class="form-control" value="{{ $settings['hero_subtitle'] ?? 'Discover the best restaurants in your neighborhood' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Location Input Placeholder</label>
                            <input type="text" name="hero_location_placeholder" class="form-control" value="{{ $settings['hero_location_placeholder'] ?? 'Enter delivery location' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Search Input Placeholder</label>
                            <input type="text" name="hero_search_placeholder" class="form-control" value="{{ $settings['hero_search_placeholder'] ?? 'Search for restaurant, cuisine or dish' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Homepage Search Button Text</label>
                            <input type="text" name="hero_search_button_text" class="form-control" value="{{ $settings['hero_search_button_text'] ?? 'Search' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category Section Title</label>
                            <input type="text" name="category_section_title" class="form-control" value="{{ $settings['category_section_title'] ?? 'Explore <span style=\"color: #FF6B35;\">Categories</span>' }}">
                            <small class="text-muted">HTML tags like <code>&lt;span&gt;</code> are allowed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category Section Subtitle</label>
                            <input type="text" name="category_section_subtitle" class="form-control" value="{{ $settings['category_section_subtitle'] ?? 'Discover food by cuisines & categories' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Collections Section Title</label>
                            <input type="text" name="collection_section_title" class="form-control" value="{{ $settings['collection_section_title'] ?? 'Collections' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Collections Section Subtitle</label>
                            <input type="text" name="collection_section_subtitle" class="form-control" value="{{ $settings['collection_section_subtitle'] ?? 'Explore curated lists of top restaurants' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Restaurants Section Title</label>
                            <input type="text" name="restaurants_section_title" class="form-control" value="{{ $settings['restaurants_section_title'] ?? 'Restaurants <span style=\"color: #FF6B35;\">near you</span>' }}">
                            <small class="text-muted">HTML tags like <code>&lt;span&gt;</code> are allowed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Restaurants Section Subtitle</label>
                            <input type="text" name="restaurants_section_subtitle" class="form-control" value="{{ $settings['restaurants_section_subtitle'] ?? 'Discover the best restaurants in your area' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Partner Nav Link Text</label>
                            <input type="text" name="partner_nav_text" class="form-control" value="{{ $settings['partner_nav_text'] ?? 'Partner with Us' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Partner Modal Title</label>
                            <input type="text" name="partner_modal_title" class="form-control" value="{{ $settings['partner_modal_title'] ?? 'Partner with ' . ($settings['app_name'] ?? 'FoodFlow') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Partner Modal Subtitle</label>
                            <input type="text" name="partner_modal_subtitle" class="form-control" value="{{ $settings['partner_modal_subtitle'] ?? 'Choose a partner journey and grow with our delivery network.' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Restaurant Partner Card Title</label>
                            <input type="text" name="partner_restaurant_title" class="form-control" value="{{ $settings['partner_restaurant_title'] ?? 'Restaurant Partner' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Restaurant Partner Card Text</label>
                            <input type="text" name="partner_restaurant_text" class="form-control" value="{{ $settings['partner_restaurant_text'] ?? 'List your restaurant & reach thousands of customers' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Delivery Partner Card Title</label>
                            <input type="text" name="partner_driver_title" class="form-control" value="{{ $settings['partner_driver_title'] ?? 'Delivery Partner' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Delivery Partner Card Text</label>
                            <input type="text" name="partner_driver_text" class="form-control" value="{{ $settings['partner_driver_text'] ?? 'Earn money by delivering on your own schedule' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Description</label>
                            <input type="text" name="footer_description" class="form-control" value="{{ $settings['footer_description'] ?? 'Order food from the best restaurants in your city. Fast delivery, great taste!' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Copyright Text</label>
                            <input type="text" name="footer_copyright" class="form-control" value="{{ $settings['footer_copyright'] ?? 'All rights reserved.' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Company Title</label>
                            <input type="text" name="footer_company_title" class="form-control" value="{{ $settings['footer_company_title'] ?? 'Company' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Support Title</label>
                            <input type="text" name="footer_support_title" class="form-control" value="{{ $settings['footer_support_title'] ?? 'Support' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Legal Title</label>
                            <input type="text" name="footer_legal_title" class="form-control" value="{{ $settings['footer_legal_title'] ?? 'Legal' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer About Link Text</label>
                            <input type="text" name="footer_link_about" class="form-control" value="{{ $settings['footer_link_about'] ?? 'About Us' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Careers Link Text</label>
                            <input type="text" name="footer_link_careers" class="form-control" value="{{ $settings['footer_link_careers'] ?? 'Careers' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Blog Link Text</label>
                            <input type="text" name="footer_link_blog" class="form-control" value="{{ $settings['footer_link_blog'] ?? 'Blog' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Help Link Text</label>
                            <input type="text" name="footer_link_help" class="form-control" value="{{ $settings['footer_link_help'] ?? 'Help Center' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer Contact Link Text</label>
                            <input type="text" name="footer_link_contact" class="form-control" value="{{ $settings['footer_link_contact'] ?? 'Contact Us' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Footer FAQs Link Text</label>
                            <input type="text" name="footer_link_faqs" class="form-control" value="{{ $settings['footer_link_faqs'] ?? 'FAQs' }}">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Homepage Content</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
