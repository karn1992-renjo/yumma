<style>
    .settings-shell {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .settings-hero,
    .settings-card,
    .settings-link-card,
    .settings-shell .table-card {
        background: #fff;
        border: 1px solid rgba(148, 163, 184, .22);
        border-radius: 18px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .07);
    }

    .settings-hero {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 22px;
        overflow: hidden;
    }

    .settings-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 12px;
        margin-bottom: 12px;
        border: 1px solid rgba(99, 102, 241, .18);
        border-radius: 999px;
        color: #4f46e5;
        background: #eef2ff;
        font-size: .78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .settings-hero h1 {
        margin: 0;
        color: #0f172a;
        font-size: clamp(1.45rem, 2.2vw, 2rem);
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1.1;
    }

    .settings-hero p,
    .settings-card .text-muted,
    .settings-link-card p {
        color: #64748b !important;
    }

    .settings-hero p {
        margin: 8px 0 0;
        max-width: 760px;
        font-size: .94rem;
        line-height: 1.55;
    }

    .settings-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 4px 2px 10px;
        scrollbar-width: thin;
    }

    .settings-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        padding: 9px 13px;
        border: 1px solid rgba(148, 163, 184, .28);
        border-radius: 12px;
        background: #fff;
        color: #475569;
        font-size: .88rem;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        transition: all .18s ease;
    }

    .settings-tab:hover,
    .settings-tab.active {
        color: #fff;
        border-color: #4f46e5;
        background: #4f46e5;
        box-shadow: 0 10px 24px rgba(79, 70, 229, .2);
    }

    .settings-card,
    .settings-shell .table-card {
        overflow: hidden;
    }

    .settings-shell .table-card .card-header {
        padding: 18px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, .22);
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%) !important;
    }

    .settings-shell .table-card > .p-4 {
        padding: 20px !important;
    }

    .settings-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, .22);
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    }

    .settings-card-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.02rem;
        font-weight: 900;
        letter-spacing: 0;
    }

    .settings-card-subtitle {
        margin: 4px 0 0;
        color: #64748b;
        font-size: .84rem;
        line-height: 1.45;
    }

    .settings-card-body {
        padding: 20px;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 16px;
    }

    .settings-span-12 { grid-column: span 12; }
    .settings-span-8 { grid-column: span 8; }
    .settings-span-6 { grid-column: span 6; }
    .settings-span-4 { grid-column: span 4; }
    .settings-span-3 { grid-column: span 3; }

    .settings-field label,
    .settings-card .form-label {
        margin-bottom: 7px;
        color: #334155;
        font-size: .82rem;
        font-weight: 850;
    }

    .settings-card .form-control,
    .settings-card .form-select {
        min-height: 46px;
        border-color: #dbe5f2;
        border-radius: 12px;
        color: #0f172a;
        font-size: .92rem;
        font-weight: 650;
        box-shadow: none;
    }

    .settings-card textarea.form-control {
        min-height: 120px;
    }

    .settings-card .form-control:focus,
    .settings-card .form-select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 .22rem rgba(99, 102, 241, .12);
    }

    .settings-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 8px 0 14px;
        color: #0f172a;
        font-size: .95rem;
        font-weight: 900;
    }

    .settings-section-title::before {
        content: "";
        width: 8px;
        height: 24px;
        border-radius: 999px;
        background: #ff5a1f;
    }

    .settings-action-bar {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
        padding-top: 18px;
        border-top: 1px solid rgba(148, 163, 184, .22);
    }

    .settings-link-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .settings-link-card {
        display: flex;
        align-items: center;
        gap: 14px;
        min-height: 108px;
        padding: 18px;
        color: inherit;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .settings-link-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 22px 50px rgba(15, 23, 42, .1);
    }

    .settings-link-icon,
    .settings-stat-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        border-radius: 14px;
        color: #4f46e5;
        background: #eef2ff;
        font-size: 1rem;
    }

    .settings-link-card h3 {
        margin: 0;
        color: #0f172a;
        font-size: .96rem;
        font-weight: 900;
    }

    .settings-link-card p {
        margin: 4px 0 0;
        font-size: .8rem;
        line-height: 1.35;
    }

    .settings-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .settings-stat {
        padding: 16px;
        border: 1px solid rgba(148, 163, 184, .22);
        border-radius: 14px;
        background: #f8fafc;
    }

    .settings-stat-label {
        margin-top: 10px;
        color: #64748b;
        font-size: .76rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .settings-stat-value {
        margin-top: 4px;
        color: #0f172a;
        font-size: .95rem;
        font-weight: 900;
        word-break: break-word;
    }

    .settings-image-preview {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 96px;
        height: 72px;
        padding: 8px;
        border: 1px solid rgba(148, 163, 184, .28);
        border-radius: 12px;
        background: #f8fafc;
        overflow: hidden;
    }

    .settings-image-preview.is-icon {
        width: 56px;
        height: 56px;
    }

    .settings-image-preview.is-favicon {
        width: 44px;
        height: 44px;
        padding: 6px;
    }

    .settings-image-preview.is-wide {
        width: 180px;
        height: 96px;
        padding: 0;
    }

    .settings-image-preview img {
        display: block;
        width: 100% !important;
        height: 100% !important;
        max-width: 100% !important;
        max-height: 100% !important;
        object-fit: contain;
    }

    .settings-image-preview.is-wide img {
        object-fit: cover;
    }

    .settings-card .alert {
        border-radius: 14px;
    }

    .settings-card .table {
        margin-bottom: 0;
    }

    .settings-card .table th {
        color: #64748b;
        font-size: .76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
        background: #f8fafc;
    }

    @media (max-width: 1199.98px) {
        .settings-link-grid,
        .settings-stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .settings-shell {
            gap: 14px;
        }

        .settings-hero,
        .settings-card-header,
        .settings-action-bar {
            align-items: stretch;
            flex-direction: column;
        }

        .settings-hero,
        .settings-card-body {
            padding: 16px;
        }

        .settings-card-header {
            padding: 16px;
        }

        .settings-link-grid,
        .settings-stat-grid {
            grid-template-columns: 1fr;
        }

        .settings-grid {
            grid-template-columns: 1fr;
        }

        .settings-span-12,
        .settings-span-8,
        .settings-span-6,
        .settings-span-4,
        .settings-span-3 {
            grid-column: 1 / -1;
        }

        .settings-action-bar .btn {
            width: 100%;
        }
    }
</style>
