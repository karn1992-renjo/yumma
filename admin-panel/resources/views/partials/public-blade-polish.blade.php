<style>
    :root {
        --ff-primary: {{ App\Models\AppSetting::getValue('primary_color', '#FF6B35') }};
        --ff-secondary: {{ App\Models\AppSetting::getValue('secondary_color', '#FF8C42') }};
        --ff-ink: #111827;
        --ff-muted: #64748b;
        --ff-border: #e2e8f0;
        --ff-surface: rgba(255, 255, 255, 0.92);
    }

    body {
        background:
            radial-gradient(circle at top left, color-mix(in srgb, var(--ff-primary) 16%, transparent), transparent 34%),
            radial-gradient(circle at top right, color-mix(in srgb, var(--ff-secondary) 15%, transparent), transparent 30%),
            linear-gradient(180deg, #fffaf7 0%, #ffffff 42%, #f8fafc 100%);
        color: var(--ff-ink);
    }

    nav,
    .navbar,
    header:not(.hero):not(.hero-section) {
        backdrop-filter: blur(18px);
    }

    .container > .card,
    .container .card,
    .content-card,
    .section-card,
    .checkout-card,
    .payment-card,
    .restaurant-card,
    .feature-card,
    .blog-card,
    .career-card,
    .faq-item,
    .contact-card,
    .legal-card,
    .auth-card,
    .register-card,
    .summary-card,
    .order-summary,
    .address-card {
        border: 1px solid rgba(226, 232, 240, 0.86) !important;
        border-radius: 28px !important;
        background: var(--ff-surface) !important;
        box-shadow: 0 24px 64px rgba(15, 23, 42, 0.09) !important;
        backdrop-filter: blur(16px);
    }

    .hero,
    .hero-section,
    .page-hero,
    .section-hero,
    .auth-hero {
        position: relative;
        overflow: hidden;
        border-radius: clamp(24px, 4vw, 38px);
        background:
            radial-gradient(circle at 14% 18%, rgba(255, 255, 255, 0.24), transparent 30%),
            linear-gradient(135deg, #111827 0%, color-mix(in srgb, var(--ff-primary) 60%, #111827) 56%, var(--ff-secondary) 100%) !important;
        color: #ffffff;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.22);
    }

    .hero::after,
    .hero-section::after,
    .page-hero::after,
    .section-hero::after,
    .auth-hero::after {
        content: "";
        position: absolute;
        width: 230px;
        height: 230px;
        right: -70px;
        bottom: -90px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.15);
    }

    h1,
    h2,
    h3,
    .display-1,
    .display-2,
    .display-3,
    .display-4 {
        letter-spacing: -0.045em;
    }

    .btn,
    button,
    input[type="submit"] {
        border-radius: 999px !important;
        font-weight: 800 !important;
        letter-spacing: -0.01em;
        transition: transform 0.22s ease, box-shadow 0.22s ease;
    }

    .btn:hover,
    button:hover,
    input[type="submit"]:hover {
        transform: translateY(-1px);
    }

    .btn-primary,
    .primary-btn,
    button[type="submit"],
    input[type="submit"] {
        border: 0 !important;
        color: #ffffff !important;
        background: linear-gradient(135deg, var(--ff-primary), var(--ff-secondary)) !important;
        box-shadow: 0 18px 38px color-mix(in srgb, var(--ff-primary) 28%, transparent) !important;
    }

    .form-control,
    .form-select,
    input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
    select,
    textarea {
        min-height: 48px;
        border: 1px solid rgba(203, 213, 225, 0.95) !important;
        border-radius: 16px !important;
        background: rgba(255, 255, 255, 0.94) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
    }

    .form-control:focus,
    .form-select:focus,
    input:focus,
    select:focus,
    textarea:focus {
        border-color: var(--ff-primary) !important;
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--ff-primary) 16%, transparent) !important;
        outline: 0 !important;
    }

    label {
        color: var(--ff-ink);
        font-weight: 800;
    }

    .table {
        overflow: hidden;
        border-radius: 22px;
    }

    .table thead th {
        border: 0;
        background: #fff7ed;
        color: #9a3412;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .badge,
    .pill,
    .tag,
    .status {
        border-radius: 999px !important;
        font-weight: 800 !important;
        padding: 7px 12px;
    }

    .modal-content {
        border: 0;
        border-radius: 30px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 34px 90px rgba(15, 23, 42, 0.24);
        backdrop-filter: blur(20px);
    }

    .alert {
        border: 0;
        border-radius: 20px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    }

    @media (max-width: 768px) {
        .container > .card,
        .container .card,
        .content-card,
        .section-card,
        .checkout-card,
        .payment-card,
        .restaurant-card,
        .feature-card,
        .blog-card,
        .career-card,
        .faq-item,
        .contact-card,
        .legal-card,
        .auth-card,
        .register-card,
        .summary-card,
        .order-summary,
        .address-card {
            border-radius: 22px !important;
        }
    }
</style>
