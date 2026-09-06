@once
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WNM5CMBQ" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<script>
    (function (window, document) {
        const containerId = 'GTM-WNM5CMBQ';
        let loaded = false;

        function loadGoogleTagManager() {
            if (loaded) return;
            loaded = true;
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });

            const script = document.createElement('script');
            script.async = true;
            script.src = 'https://www.googletagmanager.com/gtm.js?id=' + containerId;
            document.head.appendChild(script);
        }

        function scheduleGoogleTagManager() {
            const start = function () {
                if ('requestIdleCallback' in window) {
                    window.requestIdleCallback(loadGoogleTagManager, { timeout: 3000 });
                } else {
                    window.setTimeout(loadGoogleTagManager, 1500);
                }
            };

            ['pointerdown', 'keydown', 'touchstart'].forEach(function (eventName) {
                window.addEventListener(eventName, start, { once: true, passive: true });
            });
            window.setTimeout(start, 3000);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scheduleGoogleTagManager, { once: true });
        } else {
            scheduleGoogleTagManager();
        }
    })(window, document);
</script>
@endonce
