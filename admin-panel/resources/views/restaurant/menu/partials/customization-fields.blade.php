@php
    $optionGroups = [
        [
            'field' => 'variants',
            'title' => 'Variants (Size / Quantity)',
            'button' => 'Add Variant',
            'placeholder' => 'Medium / 500g',
            'help' => 'Customers must choose one available variant when variants are configured.',
        ],
        [
            'field' => 'add_ons',
            'title' => 'Add-ons / Extras',
            'button' => 'Add Extra',
            'placeholder' => 'Extra cheese',
            'help' => 'Customers can select multiple available extras during add-to-cart.',
        ],
    ];

    $normalizeOptionsForForm = function ($items) {
        return collect($items ?? [])
            ->map(function ($item) {
                if (is_string($item)) {
                    return [
                        'name' => $item,
                        'price' => 0,
                        'is_available' => true,
                        'custom_fields_text' => '',
                    ];
                }

                if (!is_array($item)) {
                    return null;
                }

                $customFields = collect($item['custom_fields'] ?? [])
                    ->map(fn ($value, $key) => $key . ': ' . $value)
                    ->implode("\n");

                return [
                    'name' => $item['name'] ?? $item['label'] ?? '',
                    'price' => $item['price'] ?? 0,
                    'is_available' => array_key_exists('is_available', $item) ? (bool) $item['is_available'] : true,
                    'custom_fields_text' => $item['custom_fields_text'] ?? $customFields,
                ];
            })
            ->filter(fn ($item) => $item && trim((string) $item['name']) !== '')
            ->values()
            ->all();
    };
@endphp

@foreach($optionGroups as $group)
    @php($sourceOptions = $group['field'] === 'variants' ? ($variants ?? []) : ($add_ons ?? []))
    @php($initialOptions = $normalizeOptionsForForm(old($group['field'], $sourceOptions)))
    <div class="mt-4 menu-option-editor" data-menu-option-editor="{{ $group['field'] }}" data-menu-option-id-prefix="{{ $optionIdPrefix ?? '' }}">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
            <div>
                <label class="form-label fw-semibold mb-1">{{ $group['title'] }}</label>
                <small class="text-muted d-block">{{ $group['help'] }}</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-3" data-add-menu-option>
                <i class="fas fa-plus me-1"></i>{{ $group['button'] }}
            </button>
        </div>

        <div class="d-grid gap-2" data-menu-option-list></div>
        <script type="application/json" data-menu-option-initial>@json($initialOptions)</script>

        @error($group['field'])
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
    </div>
@endforeach

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const optionLabels = {
                variants: {
                    placeholder: 'Medium / 500g',
                    empty: 'No variants added. Add sizes, weights, portions, or quantity choices.',
                },
                add_ons: {
                    placeholder: 'Extra cheese',
                    empty: 'No extras added. Add toppings, sides, sauces, or paid extras.',
                },
            };

            const rowHtml = (field, index, option = {}, idPrefix = '') => {
                const isAvailable = option.is_available !== false && option.is_available !== 0 && option.is_available !== '0';
                const availableId = `${idPrefix}${field}_${index}_available`;

                return `
                    <div class="border rounded-3 p-3 bg-light" data-menu-option-row>
                        <div class="row g-2 align-items-start">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Name</label>
                                <input type="text" name="${field}[${index}][name]" class="form-control" value="${escapeHtml(option.name)}" placeholder="${optionLabels[field].placeholder}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Extra Price</label>
                                <input type="number" name="${field}[${index}][price]" class="form-control" value="${escapeHtml(option.price ?? 0)}" min="0" step="{{ $priceStep ?? '0.01' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Custom Fields</label>
                                <textarea name="${field}[${index}][custom_fields_text]" class="form-control" rows="2" placeholder="Portion: 2 slices&#10;Unit: 500g">${escapeHtml(option.custom_fields_text)}</textarea>
                            </div>
                            <div class="col-md-1 d-flex justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-3 mt-md-4" data-remove-menu-option aria-label="Remove option">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="${field}[${index}][is_available]" value="0">
                            <input type="checkbox" name="${field}[${index}][is_available]" value="1" class="form-check-input" id="${availableId}" ${isAvailable ? 'checked' : ''}>
                            <label class="form-check-label small fw-semibold" for="${availableId}">Available to customers</label>
                        </div>
                    </div>
                `;
            };

            document.querySelectorAll('[data-menu-option-editor]').forEach((editor) => {
                const field = editor.dataset.menuOptionEditor;
                const list = editor.querySelector('[data-menu-option-list]');
                const initialScript = editor.querySelector('[data-menu-option-initial]');
                const addButton = editor.querySelector('[data-add-menu-option]');
                const idPrefix = editor.dataset.menuOptionIdPrefix || '';
                let nextIndex = 0;

                const renderEmpty = () => {
                    if (list.children.length > 0) return;
                    list.innerHTML = `<div class="text-muted small border rounded-3 p-3 bg-light" data-menu-option-empty>${optionLabels[field].empty}</div>`;
                };

                const addRow = (option = {}) => {
                    const empty = list.querySelector('[data-menu-option-empty]');
                    if (empty) empty.remove();

                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = rowHtml(field, nextIndex, option, idPrefix).trim();
                    list.appendChild(wrapper.firstElementChild);
                    nextIndex += 1;
                };

                try {
                    const initialOptions = JSON.parse(initialScript.textContent || '[]');
                    initialOptions.forEach((option) => addRow(option));
                } catch (error) {
                    console.warn('Unable to load menu customization rows', error);
                }

                renderEmpty();

                addButton.addEventListener('click', () => addRow({
                    name: '',
                    price: 0,
                    is_available: true,
                    custom_fields_text: '',
                }));

                list.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-remove-menu-option]');
                    if (!button) return;
                    button.closest('[data-menu-option-row]').remove();
                    renderEmpty();
                });

                editor.addEventListener('menu-options:set', (event) => {
                    list.innerHTML = '';
                    nextIndex = 0;

                    const options = Array.isArray(event.detail?.options) ? event.detail.options : [];
                    options.forEach((option) => {
                        const customFields = option?.custom_fields && typeof option.custom_fields === 'object'
                            ? Object.entries(option.custom_fields).map(([key, value]) => `${key}: ${value}`).join('\n')
                            : '';

                        addRow({
                            name: option?.name || option?.label || '',
                            price: option?.price ?? option?.additional_price ?? 0,
                            is_available: option?.is_available ?? true,
                            custom_fields_text: option?.custom_fields_text ?? customFields,
                        });
                    });

                    renderEmpty();
                });
            });
        });
    </script>
@endonce
