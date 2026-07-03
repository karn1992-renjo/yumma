<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
        $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    @endphp

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
            <script>
        window.currencySymbol = @json($currencySymbol);
        window.currencyDecimals = @json($currencyDecimals);
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
        <style>
            :root {
                --ff-primary: #ff6b35;
                --ff-secondary: #ff9f1c;
                --ff-ink: #111827;
                --ff-muted: #64748b;
                --ff-border: #e2e8f0;
            }

            body {
                min-height: 100vh;
                margin: 0;
                background:
                    radial-gradient(circle at 12% 12%, rgba(255, 107, 53, 0.2), transparent 30%),
                    radial-gradient(circle at 88% 18%, rgba(255, 159, 28, 0.2), transparent 28%),
                    linear-gradient(135deg, #fff7ed 0%, #ffffff 45%, #f8fafc 100%);
                color: var(--ff-ink);
            }

            body::before {
                content: "";
                position: fixed;
                inset: 0;
                pointer-events: none;
                background-image:
                    linear-gradient(rgba(15, 23, 42, 0.035) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(15, 23, 42, 0.035) 1px, transparent 1px);
                background-size: 34px 34px;
                mask-image: linear-gradient(to bottom, rgba(0,0,0,0.75), transparent 78%);
            }

            .font-sans {
                position: relative;
                z-index: 1;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 32px 16px;
            }

            .font-sans > * {
                width: min(100%, 480px);
            }

            .min-h-screen,
            .sm\:max-w-md,
            .w-full,
            .bg-white,
            .shadow-md,
            .overflow-hidden {
                border-radius: 30px !important;
            }

            .bg-white,
            .shadow-md,
            .shadow-xl,
            form,
            [class*="shadow"] {
                border: 1px solid rgba(226, 232, 240, 0.86);
                background: rgba(255, 255, 255, 0.92) !important;
                box-shadow: 0 28px 80px rgba(15, 23, 42, 0.13) !important;
                backdrop-filter: blur(18px);
            }

            label,
            .block.font-medium {
                color: var(--ff-ink) !important;
                font-weight: 800 !important;
                letter-spacing: -0.01em;
            }

            input,
            select,
            textarea {
                min-height: 48px;
                border-radius: 16px !important;
                border-color: rgba(203, 213, 225, 0.95) !important;
                background: rgba(255, 255, 255, 0.95) !important;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            input:focus,
            select:focus,
            textarea:focus {
                border-color: var(--ff-primary) !important;
                box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.16) !important;
                outline: none !important;
            }

            button,
            a[class*="button"],
            .inline-flex {
                border-radius: 999px !important;
                font-weight: 800 !important;
                letter-spacing: -0.01em;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            button:hover,
            a[class*="button"]:hover,
            .inline-flex:hover {
                transform: translateY(-1px);
            }

            button[type="submit"],
            .bg-gray-800,
            .bg-gray-900,
            .bg-indigo-600 {
                border: 0 !important;
                color: #ffffff !important;
                background: linear-gradient(135deg, var(--ff-primary), var(--ff-secondary)) !important;
                box-shadow: 0 18px 38px rgba(255, 107, 53, 0.26) !important;
            }

            a {
                color: var(--ff-primary);
                font-weight: 700;
            }

            .text-gray-600,
            .text-gray-500,
            .text-sm {
                color: var(--ff-muted) !important;
            }

            .text-red-600,
            .text-red-500 {
                color: #ef4444 !important;
            }

            svg {
                filter: drop-shadow(0 12px 22px rgba(255, 107, 53, 0.16));
            }

            @media (max-width: 640px) {
                .font-sans {
                    padding: 18px 12px;
                    place-items: start center;
                }

                .min-h-screen,
                .sm\:max-w-md,
                .w-full,
                .bg-white,
                .shadow-md,
                .overflow-hidden {
                    border-radius: 22px !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="font-sans text-gray-900 antialiased">
            {{ $slot }}
        </div>

        @livewireScripts
        @include('partials.web-visit-tracker', ['panel' => 'guest'])
    </body>
</html>
