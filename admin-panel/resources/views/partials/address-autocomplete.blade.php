{{--
    Shared, reusable Google Places autocomplete helper.

    Include this once per page (it only needs the Maps JS API with the
    `places` library loaded — most pages already load that via
    `partials.google-maps-shim` or their own <script src="...maps/api/js...">).

    Usage from any form, once `google.maps.places` is available:

        AddressAutocomplete.bind('locationSearch', {
            address: 'address',
            city: 'city',
            state: 'state',
            pincode: 'pincode',
            lat: 'latitude',
            lng: 'longitude',
        }, {
            onPlace: function (place) {
                // optional: update this page's own map/marker with place.geometry.location
            },
        });

    Every key in the field map is optional — AddressAutocomplete only fills
    a field when both the key is provided AND an element with that id
    actually exists on the page, so the same helper serves a full
    "restaurant" form (address/city/state/pincode/lat/lng) and a
    "delivery zone" form (lat/lng only) without any per-form branching.
--}}
<script>
window.AddressAutocomplete = (function () {
    function field(id) {
        return id ? document.getElementById(id) : null;
    }

    function componentValue(place, types) {
        const components = place.address_components || [];
        const match = components.find((item) => types.some((type) => item.types.includes(type)));
        return match ? match.long_name : '';
    }

    function fillFields(place, fieldMap) {
        if (!place || !fieldMap) return;

        const formattedAddress = place.formatted_address || place.name || '';
        const addressEl = field(fieldMap.address);
        if (addressEl && formattedAddress) addressEl.value = formattedAddress;

        const cityEl = field(fieldMap.city);
        if (cityEl) {
            const city = componentValue(place, ['locality', 'postal_town', 'administrative_area_level_3', 'sublocality']);
            if (city) cityEl.value = city;
        }

        const stateEl = field(fieldMap.state);
        if (stateEl) {
            const state = componentValue(place, ['administrative_area_level_1']);
            if (state) stateEl.value = state;
        }

        const pincodeEl = field(fieldMap.pincode);
        if (pincodeEl) {
            const pincode = componentValue(place, ['postal_code']);
            if (pincode) pincodeEl.value = pincode;
        }

        const location = place.geometry && place.geometry.location;
        if (location) {
            const latEl = field(fieldMap.lat);
            const lngEl = field(fieldMap.lng);
            if (latEl) latEl.value = location.lat().toFixed(7);
            if (lngEl) lngEl.value = location.lng().toFixed(7);
        }
    }

    /**
     * Bind live Places autocomplete suggestions to an <input>, filling
     * whichever target fields are present on the page when a suggestion
     * is picked.
     *
     * @param {string} inputId    id of the text input to attach suggestions to
     * @param {object} fieldMap   { address, city, state, pincode, lat, lng } — all optional
     * @param {object} [opts]
     * @param {function} [opts.onPlace] called with the selected place after fields are filled
     * @param {function} [opts.onNoLocation] called if a suggestion has no geometry
     * @param {google.maps.Map} [opts.map] optional map to bind autocomplete bounds to
     * @returns {google.maps.places.Autocomplete|null}
     */
    function bind(inputId, fieldMap, opts) {
        opts = opts || {};
        const input = field(inputId);
        if (!input) return null;
        if (!window.google || !google.maps || !google.maps.places) {
            console.warn('AddressAutocomplete: google.maps.places is not loaded yet.');
            return null;
        }

        const autocomplete = new google.maps.places.Autocomplete(input, {
            fields: ['address_components', 'formatted_address', 'geometry', 'name'],
        });

        if (opts.map) autocomplete.bindTo('bounds', opts.map);

        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();
            if (!place || !place.geometry || !place.geometry.location) {
                if (typeof opts.onNoLocation === 'function') opts.onNoLocation();
                return;
            }
            fillFields(place, fieldMap || {});
            if (typeof opts.onPlace === 'function') opts.onPlace(place);
        });

        return autocomplete;
    }

    return { bind: bind, fillFields: fillFields };
})();
</script>
