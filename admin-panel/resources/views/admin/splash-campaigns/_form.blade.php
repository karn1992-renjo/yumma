@php
    $campaign = $campaign ?? null;
@endphp

<div class="sc-shell">
    <div class="sc-fields">
        <div class="mb-3">
            <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
            <input type="text" name="title" id="scTitle" class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $campaign->title ?? '') }}" required placeholder="e.g., Diwali Dhamaka 2026">
            <small class="text-muted">Internal label only -- not shown to customers.</small>
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Headline</label>
            <input type="text" name="headline" id="scHeadline" class="form-control @error('headline') is-invalid @enderror"
                   value="{{ old('headline', $campaign->headline ?? '') }}" maxlength="120" placeholder="Flat 50% Off This Diwali">
            @error('headline') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Subtitle</label>
            <input type="text" name="subtitle" id="scSubtitle" class="form-control @error('subtitle') is-invalid @enderror"
                   value="{{ old('subtitle', $campaign->subtitle ?? '') }}" maxlength="200" placeholder="On your first 3 orders">
            @error('subtitle') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Full-Screen Artwork {{ $campaign ? '' : '*' }}</label>
            <input type="file" name="image" id="scImage" class="form-control @error('image') is-invalid @enderror"
                   accept="image/png,image/jpeg,image/webp" {{ $campaign ? '' : 'required' }}>
            <small class="text-muted">Portrait artwork works best (e.g. 1080x1920). JPG, PNG or WebP, max 5MB.</small>
            @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Logo (optional)</label>
            <input type="file" name="logo" id="scLogo" class="form-control @error('logo') is-invalid @enderror" accept="image/*">
            @if($campaign?->logo_path)
                <div class="form-check mt-2">
                    <input type="checkbox" name="remove_logo" class="form-check-input" id="scRemoveLogo">
                    <label class="form-check-label" for="scRemoveLogo">Remove current logo</label>
                </div>
            @endif
            @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Background Color</label>
                <div class="input-group">
                    <span class="input-group-text p-1">
                        <input type="color" id="scBgPicker" class="form-control form-control-color border-0"
                               value="{{ old('background_color', $campaign->background_color ?? '#5C0298') }}">
                    </span>
                    <input type="text" name="background_color" id="scBgHex" class="form-control @error('background_color') is-invalid @enderror"
                           value="{{ old('background_color', $campaign->background_color ?? '#5C0298') }}" placeholder="#5C0298">
                </div>
                <small class="text-muted">Letterbox color behind the artwork and gradient overlay.</small>
                @error('background_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Display Duration (seconds) <span class="text-danger">*</span></label>
                <input type="number" name="display_duration_seconds" min="2" max="15" class="form-control @error('display_duration_seconds') is-invalid @enderror"
                       value="{{ old('display_duration_seconds', $campaign->display_duration_seconds ?? 4) }}" required>
                @error('display_duration_seconds') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Target Platform <span class="text-danger">*</span></label>
                <select name="platform" class="form-select @error('platform') is-invalid @enderror">
                    @foreach(['both' => 'Android + iOS', 'android' => 'Android Only', 'ios' => 'iOS Only'] as $value => $label)
                        <option value="{{ $value }}" {{ old('platform', $campaign->platform ?? 'both') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('platform') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Display Order</label>
                <input type="number" name="sort_order" min="0" class="form-control @error('sort_order') is-invalid @enderror"
                       value="{{ old('sort_order', $campaign->sort_order ?? 0) }}">
                <small class="text-muted">If more than one campaign is eligible at once, the highest order wins.</small>
                @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Start Date &amp; Time</label>
                <input type="datetime-local" name="start_at" class="form-control @error('start_at') is-invalid @enderror"
                       value="{{ old('start_at', $campaign?->start_at?->format('Y-m-d\TH:i')) }}">
                <small class="text-muted">Server time. Leave blank to start immediately once activated.</small>
                @error('start_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">End Date &amp; Time</label>
                <input type="datetime-local" name="end_at" class="form-control @error('end_at') is-invalid @enderror"
                       value="{{ old('end_at', $campaign?->end_at?->format('Y-m-d\TH:i')) }}">
                <small class="text-muted">Leave blank for no expiry.</small>
                @error('end_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">On Tap</label>
            <select name="tap_action" id="scTapAction" class="form-select @error('tap_action') is-invalid @enderror">
                <option value="none" {{ old('tap_action', $campaign->tap_action ?? 'none') === 'none' ? 'selected' : '' }}>Do nothing (just dismiss)</option>
                <option value="home" {{ old('tap_action', $campaign->tap_action ?? '') === 'home' ? 'selected' : '' }}>Open Home</option>
                <option value="offers" {{ old('tap_action', $campaign->tap_action ?? '') === 'offers' ? 'selected' : '' }}>Open Offers</option>
                <option value="restaurant" {{ old('tap_action', $campaign->tap_action ?? '') === 'restaurant' ? 'selected' : '' }}>Open a Restaurant</option>
                <option value="gold" {{ old('tap_action', $campaign->tap_action ?? '') === 'gold' ? 'selected' : '' }}>Open Gold Membership</option>
            </select>
            @error('tap_action') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3" id="scTapRestaurantWrap">
            <label class="form-label fw-semibold">Restaurant</label>
            <select name="tap_restaurant_id" class="form-select @error('tap_restaurant_id') is-invalid @enderror">
                <option value="">Select restaurant</option>
                @foreach($restaurants as $restaurant)
                    <option value="{{ $restaurant->id }}" {{ (string) old('tap_restaurant_id', $campaign->tap_restaurant_id ?? '') === (string) $restaurant->id ? 'selected' : '' }}>{{ $restaurant->name }}</option>
                @endforeach
            </select>
            @error('tap_restaurant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="scIsActive"
                   {{ old('is_active', $campaign->is_active ?? false) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="scIsActive">Active</label>
            <div class="form-text">Only one eligible campaign is shown at a time -- inactive, scheduled-future, or expired campaigns are never served.</div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-save me-2"></i> {{ $campaign ? 'Update Campaign' : 'Create Campaign' }}
        </button>
    </div>

    <aside class="sc-preview-pane">
        <div class="sc-phone">
            <div class="sc-phone-screen" id="scPreviewScreen">
                <img id="scPreviewImage" src="{{ $campaign?->image_path ? \App\Services\MediaStorage::url($campaign->image_path) : '' }}"
                     class="sc-preview-image {{ $campaign?->image_path ? '' : 'd-none' }}" alt="">
                <img id="scPreviewLogo" src="{{ $campaign?->logo_path ? \App\Services\MediaStorage::url($campaign->logo_path) : '' }}"
                     class="sc-preview-logo {{ $campaign?->logo_path ? '' : 'd-none' }}" alt="">
                <div class="sc-preview-text">
                    <div class="sc-preview-headline" id="scPreviewHeadline">{{ $campaign->headline ?? 'Your headline here' }}</div>
                    <div class="sc-preview-subtitle" id="scPreviewSubtitle">{{ $campaign->subtitle ?? 'Your subtitle here' }}</div>
                </div>
            </div>
        </div>
        <p class="text-muted text-center small mt-2 mb-0">Live preview -- updates as you type.</p>
    </aside>
</div>

<style>
    .sc-shell { display: grid; grid-template-columns: minmax(0, 1fr) 260px; gap: 24px; align-items: start; }
    .sc-preview-pane { position: sticky; top: 90px; }
    .sc-phone { width: 230px; height: 470px; margin: 0 auto; border-radius: 32px; background: #111; padding: 10px; box-shadow: 0 18px 40px rgba(15,23,42,.22); }
    .sc-phone-screen { position: relative; width: 100%; height: 100%; border-radius: 24px; overflow: hidden; background: {{ old('background_color', $campaign->background_color ?? '#5C0298') }}; display: flex; align-items: center; justify-content: center; }
    .sc-preview-image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .sc-preview-logo { position: absolute; top: 20px; left: 50%; transform: translateX(-50%); width: 56px; height: 56px; object-fit: contain; }
    .sc-preview-text { position: absolute; left: 0; right: 0; bottom: 0; padding: 18px 14px 26px; text-align: center; background: linear-gradient(to top, rgba(0,0,0,.65), transparent); }
    .sc-preview-headline { color: #fff; font-weight: 800; font-size: 1.05rem; line-height: 1.2; text-shadow: 0 2px 6px rgba(0,0,0,.4); }
    .sc-preview-subtitle { color: rgba(255,255,255,.88); font-weight: 600; font-size: .8rem; margin-top: 4px; text-shadow: 0 2px 6px rgba(0,0,0,.4); }
    @media (max-width: 992px) { .sc-shell { grid-template-columns: 1fr; } .sc-preview-pane { position: static; margin-top: 20px; } }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const titleInput = document.getElementById('scTitle');
        const headlineInput = document.getElementById('scHeadline');
        const subtitleInput = document.getElementById('scSubtitle');
        const imageInput = document.getElementById('scImage');
        const logoInput = document.getElementById('scLogo');
        const bgPicker = document.getElementById('scBgPicker');
        const bgHex = document.getElementById('scBgHex');
        const screenEl = document.getElementById('scPreviewScreen');
        const previewImage = document.getElementById('scPreviewImage');
        const previewLogo = document.getElementById('scPreviewLogo');
        const previewHeadline = document.getElementById('scPreviewHeadline');
        const previewSubtitle = document.getElementById('scPreviewSubtitle');
        const tapAction = document.getElementById('scTapAction');
        const tapRestaurantWrap = document.getElementById('scTapRestaurantWrap');

        headlineInput.addEventListener('input', () => {
            previewHeadline.textContent = headlineInput.value || 'Your headline here';
        });
        subtitleInput.addEventListener('input', () => {
            previewSubtitle.textContent = subtitleInput.value || 'Your subtitle here';
        });
        imageInput.addEventListener('change', () => {
            const file = imageInput.files?.[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                previewImage.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });
        logoInput.addEventListener('change', () => {
            const file = logoInput.files?.[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                previewLogo.src = e.target.result;
                previewLogo.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });
        function syncBg(value) {
            if (!/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(value)) return;
            screenEl.style.background = value;
        }
        bgPicker.addEventListener('input', () => { bgHex.value = bgPicker.value; syncBg(bgPicker.value); });
        bgHex.addEventListener('input', () => { syncBg(bgHex.value); });

        function syncTapTarget() {
            tapRestaurantWrap.style.display = tapAction.value === 'restaurant' ? '' : 'none';
        }
        tapAction.addEventListener('change', syncTapTarget);
        syncTapTarget();

        titleInput?.focus();
    });
</script>
