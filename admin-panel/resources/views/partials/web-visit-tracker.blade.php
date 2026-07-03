@once
<script>
    (function () {
        const endpoint = @json(route('visitor-track.store'));
        const csrfToken = @json(csrf_token());
        const panel = @json($panel ?? 'frontend');
        const storageKey = 'foodflow_web_session_id';
        const locationKey = 'foodflow_location_prompted_at';
        const sessionLocation = {
            latitude: @json(session('delivery_lat')),
            longitude: @json(session('delivery_lng')),
            label: @json(session('delivery_location')),
        };

        function sessionId() {
            let value = localStorage.getItem(storageKey);
            if (!value) {
                value = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
                localStorage.setItem(storageKey, value);
            }
            return value;
        }

        function countryFromLocale() {
            const locale = navigator.language || (navigator.languages || [])[0] || '';
            const countryCode = (locale.split('-')[1] || '').toUpperCase();
            if (!countryCode) return { countryCode: null, country: null };

            try {
                const names = new Intl.DisplayNames([locale], { type: 'region' });
                return { countryCode, country: names.of(countryCode) || countryCode };
            } catch (e) {
                return { countryCode, country: countryCode };
            }
        }

        function localTimeWithOffset() {
            const date = new Date();
            const offset = -date.getTimezoneOffset();
            const sign = offset >= 0 ? '+' : '-';
            const pad = (value) => String(Math.floor(Math.abs(value))).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}${sign}${pad(offset / 60)}:${pad(offset % 60)}`;
        }

        function payload(extra = {}) {
            const country = countryFromLocale();
            return {
                session_id: sessionId(),
                source: 'web',
                panel,
                url: window.location.href,
                path: window.location.pathname,
                referrer: document.referrer || null,
                country_code: country.countryCode,
                country: country.country,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || null,
                local_time: localTimeWithOffset(),
                metadata: {
                    language: navigator.language || null,
                    screen: `${window.screen.width}x${window.screen.height}`,
                    viewport: `${window.innerWidth}x${window.innerHeight}`,
                },
                ...extra,
            };
        }

        function numericCoordinate(value) {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : null;
        }

        function savedDeliveryLocation() {
            const candidates = [];

            if (sessionLocation.latitude && sessionLocation.longitude) {
                candidates.push({
                    lat: sessionLocation.latitude,
                    lng: sessionLocation.longitude,
                    label: sessionLocation.label,
                    source: 'session_delivery_location',
                });
            }

            try {
                const stored = JSON.parse(localStorage.getItem('userLocation') || 'null');
                if (stored) {
                    candidates.push({
                        lat: stored.lat || stored.latitude,
                        lng: stored.lng || stored.longitude,
                        label: stored.location || stored.address || stored.name,
                        source: 'saved_delivery_location',
                    });
                }
            } catch (e) {}

            const latInput = document.getElementById('addressLat');
            const lngInput = document.getElementById('addressLng');
            if (latInput?.value && lngInput?.value) {
                candidates.push({
                    lat: latInput.value,
                    lng: lngInput.value,
                    label: document.getElementById('addressLine')?.value || null,
                    source: 'checkout_address_location',
                });
            }

            for (const candidate of candidates) {
                const latitude = numericCoordinate(candidate.lat);
                const longitude = numericCoordinate(candidate.lng);
                if (latitude !== null && longitude !== null) {
                    return {
                        latitude,
                        longitude,
                        location_accuracy: null,
                        metadata: {
                            location_source: candidate.source,
                            location_label: candidate.label || null,
                        },
                    };
                }
            }

            return null;
        }

        function send(extra = {}) {
            fetch(endpoint, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload(extra)),
            }).catch(function () {});
        }

        function requestLocationIfAllowed() {
            if (!('geolocation' in navigator)) return;

            const lastPrompt = Number(localStorage.getItem(locationKey) || 0);
            const oneDay = 24 * 60 * 60 * 1000;
            if (Date.now() - lastPrompt < oneDay) return;
            localStorage.setItem(locationKey, String(Date.now()));

            navigator.geolocation.getCurrentPosition(function (position) {
                send({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    location_accuracy: position.coords.accuracy,
                });
            }, function () {}, {
                enableHighAccuracy: false,
                maximumAge: 30 * 60 * 1000,
                timeout: 8000,
            });
        }

        function sendSavedDeliveryLocation() {
            const location = savedDeliveryLocation();
            if (location) send(location);
        }

        function watchSetLocationCalls() {
            const originalFetch = window.fetch;
            window.fetch = function (input, init) {
                const response = originalFetch.apply(this, arguments);

                try {
                    const url = typeof input === 'string' ? input : input?.url;
                    if (url && url.includes('/set-location') && init?.body) {
                        const body = JSON.parse(init.body);
                        const latitude = numericCoordinate(body.lat);
                        const longitude = numericCoordinate(body.lng);
                        if (latitude !== null && longitude !== null) {
                            localStorage.setItem('userLocation', JSON.stringify({
                                lat: latitude,
                                lng: longitude,
                                location: body.location || null,
                            }));
                            response.then(function () {
                                send({
                                    latitude,
                                    longitude,
                                    location_accuracy: null,
                                    metadata: {
                                        location_source: 'selected_delivery_location',
                                        location_label: body.location || null,
                                    },
                                });
                            }).catch(function () {});
                        }
                    }
                } catch (e) {}

                return response;
            };
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                watchSetLocationCalls();
                send();
                setTimeout(sendSavedDeliveryLocation, 400);
                setTimeout(requestLocationIfAllowed, 1200);
            });
        } else {
            watchSetLocationCalls();
            send();
            setTimeout(sendSavedDeliveryLocation, 400);
            setTimeout(requestLocationIfAllowed, 1200);
        }
    })();
</script>
@endonce
