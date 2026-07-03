<?php

namespace App\Http\Requests\Admin;

use App\Models\HomeSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeSectionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'section_type' => ['required', Rule::in(array_keys(HomeSection::TYPES))],
            'data_source' => ['required', Rule::in(array_keys(HomeSection::SOURCES))],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'restaurant_scope' => ['nullable', Rule::in(array_keys(HomeSection::RESTAURANT_SCOPES))],
            'popular_only' => ['nullable', 'boolean'],
            'banner_ids' => ['nullable', 'array'],
            'banner_ids.*' => ['integer', 'exists:banners,id'],
            'restaurant_ids' => ['nullable', 'array'],
            'restaurant_ids.*' => ['integer', 'exists:restaurants,id'],
            'cuisine_ids' => ['nullable', 'array'],
            'cuisine_ids.*' => ['integer', 'exists:cuisines,id'],
            'global_category_ids' => ['nullable', 'array'],
            'global_category_ids.*' => ['integer', 'exists:global_menu_categories,id'],
            'menu_item_ids' => ['nullable', 'array'],
            'menu_item_ids.*' => ['integer', 'exists:master_menu_items,id'],
            'promo_code_ids' => ['nullable', 'array'],
            'promo_code_ids.*' => ['integer', 'exists:promo_codes,id'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'background_opacity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'background_image' => ['nullable', 'image', 'max:4096'],
            'remove_background_image' => ['nullable', 'boolean'],
            'hero_media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg,json', 'max:8192'],
            'remove_hero_media' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'popular_only' => $this->boolean('popular_only'),
            'remove_background_image' => $this->boolean('remove_background_image'),
            'remove_hero_media' => $this->boolean('remove_hero_media'),
        ]);
    }
}
