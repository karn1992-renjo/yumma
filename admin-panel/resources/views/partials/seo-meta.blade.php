{{-- Shared SEO head tags. Pass $metaTitle / $metaDescription / $metaImage / $canonicalUrl
     to override the global defaults from Admin -> Settings -> SEO. --}}
@php
    $__seoTitle = $metaTitle ?? ($appName ?? App\Models\AppSetting::getValue('app_name', config('app.name', 'Swado')));
    $__seoSuffix = App\Models\AppSetting::getValue('seo_meta_title_suffix', '');
    $__seoDescription = $metaDescription ?? App\Models\AppSetting::getValue('seo_meta_description', 'Order food online from the best restaurants near you. Fast delivery, live order tracking, and easy payments.');
    $__seoKeywords = $metaKeywords ?? App\Models\AppSetting::getValue('seo_meta_keywords', '');
    $__seoImageRaw = $metaImage ?? App\Models\AppSetting::getValue('seo_og_image');
    $__seoImageUrl = $__seoImageRaw
        ? (filter_var($__seoImageRaw, FILTER_VALIDATE_URL) ? $__seoImageRaw : \Illuminate\Support\Facades\Storage::disk('public')->url($__seoImageRaw))
        : asset('favicon.ico');
    $__seoCanonical = $canonicalUrl ?? url()->current();
    $__seoVerification = App\Models\AppSetting::getValue('seo_google_site_verification');
    $__seoGa4 = App\Models\AppSetting::getValue('seo_ga4_measurement_id');
    $__seoGtm = App\Models\AppSetting::getValue('seo_gtm_container_id');
@endphp
@if($__seoDescription)
<meta name="description" content="{{ $__seoDescription }}">
@endif
@if($__seoKeywords)
<meta name="keywords" content="{{ $__seoKeywords }}">
@endif
<link rel="canonical" href="{{ $__seoCanonical }}">

<meta property="og:type" content="website">
<meta property="og:title" content="{{ $__seoTitle }}{{ $__seoSuffix }}">
<meta property="og:description" content="{{ $__seoDescription }}">
<meta property="og:image" content="{{ $__seoImageUrl }}">
<meta property="og:url" content="{{ $__seoCanonical }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $__seoTitle }}{{ $__seoSuffix }}">
<meta name="twitter:description" content="{{ $__seoDescription }}">
<meta name="twitter:image" content="{{ $__seoImageUrl }}">

@if($__seoVerification)
<meta name="google-site-verification" content="{{ $__seoVerification }}">
@endif

@if($__seoGa4)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $__seoGa4 }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{{ $__seoGa4 }}');
</script>
@endif

@if($__seoGtm)
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ $__seoGtm }}');</script>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $__seoGtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
