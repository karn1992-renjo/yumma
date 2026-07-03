<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeSectionStoreRequest;
use App\Http\Requests\Admin\HomeSectionUpdateRequest;
use App\Models\Banner;
use App\Models\Cuisine;
use App\Models\GlobalMenuCategory;
use App\Models\HomeSection;
use App\Models\MasterMenuItem;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Services\HomeSectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeSectionController extends Controller
{
    public function __construct(private readonly HomeSectionService $homeSectionService)
    {
    }

    public function index(): View
    {
        $sections = $this->homeSectionService->adminSections();

        return view('admin.home-sections.index', [
            'sections' => $sections,
            'summary' => [
                'total' => $sections->count(),
                'dynamic' => $sections->where('source', 'dynamic')->count(),
                'built_in' => $sections->where('source', 'built_in')->count(),
                'active' => $sections->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.home-sections.create', $this->formData(new HomeSection([
            'section_type' => 'restaurant_grid',
            'data_source' => 'auto',
            'display_order' => 0,
            'is_active' => true,
            'configuration' => [
                'limit' => 8,
                'restaurant_scope' => 'featured',
                'restaurant_ids' => [],
                'banner_ids' => [],
                'cuisine_ids' => [],
                'global_category_ids' => [],
                'menu_item_ids' => [],
                'promo_code_ids' => [],
                'popular_only' => false,
                'background_color' => '#FFFFFF',
                'background_opacity' => 0.88,
                'background_image' => null,
                'hero_media' => null,
            ],
        ])));
    }

    public function store(HomeSectionStoreRequest $request): RedirectResponse
    {
        HomeSection::query()->create($this->payload($request->validated()));

        return redirect()->route('admin.home-sections.index')->with('success', 'Home section created successfully.');
    }

    public function edit(HomeSection $homeSection): View
    {
        return view('admin.home-sections.edit', $this->formData($homeSection));
    }

    public function update(HomeSectionUpdateRequest $request, HomeSection $homeSection): RedirectResponse
    {
        $homeSection->update($this->payload($request->validated(), $homeSection));

        return redirect()->route('admin.home-sections.index')->with('success', 'Home section updated successfully.');
    }

    public function destroy(HomeSection $homeSection): RedirectResponse
    {
        $configuration = $homeSection->configuration ?? [];
        foreach (['background_image', 'hero_media'] as $key) {
            $path = $configuration[$key] ?? null;
            if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $homeSection->delete();

        return redirect()->route('admin.home-sections.index')->with('success', 'Home section deleted successfully.');
    }

    public function reorder(): RedirectResponse
    {
        $payload = request()->validate([
            'ordered_tokens' => ['required', 'array'],
            'ordered_tokens.*' => ['required', 'string'],
        ]);

        $this->homeSectionService->reorder($payload['ordered_tokens']);

        return redirect()->route('admin.home-sections.index')->with('success', 'Homepage section order updated successfully.');
    }

    private function payload(array $validated, ?HomeSection $homeSection = null): array
    {
        $configuration = $homeSection?->configuration ?? [];
        $backgroundImagePath = $configuration['background_image'] ?? null;
        $removeBackgroundImage = (bool) ($validated['remove_background_image'] ?? false);

        if ($removeBackgroundImage && is_string($backgroundImagePath) && $backgroundImagePath !== '') {
            if (Storage::disk('public')->exists($backgroundImagePath)) {
                Storage::disk('public')->delete($backgroundImagePath);
            }
            $backgroundImagePath = null;
        }

        if (request()->hasFile('background_image')) {
            if (is_string($backgroundImagePath) && $backgroundImagePath !== '' && Storage::disk('public')->exists($backgroundImagePath)) {
                Storage::disk('public')->delete($backgroundImagePath);
            }

            $backgroundImagePath = request()->file('background_image')->store('home-sections', 'public');
        }

        $heroMediaPath = $this->uploadHeroSectionMedia(
            $configuration['hero_media'] ?? null,
            (bool) ($validated['remove_hero_media'] ?? false),
        );

        return [
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'section_type' => $validated['section_type'],
            'data_source' => $validated['data_source'],
            'display_order' => (int) ($validated['display_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'configuration' => [
                'limit' => (int) ($validated['limit'] ?? 8),
                'restaurant_scope' => $validated['restaurant_scope'] ?? 'featured',
                'popular_only' => (bool) ($validated['popular_only'] ?? false),
                'banner_ids' => array_values(array_map('intval', $validated['banner_ids'] ?? [])),
                'restaurant_ids' => array_values(array_map('intval', $validated['restaurant_ids'] ?? [])),
                'cuisine_ids' => array_values(array_map('intval', $validated['cuisine_ids'] ?? [])),
                'global_category_ids' => array_values(array_map('intval', $validated['global_category_ids'] ?? [])),
                'menu_item_ids' => array_values(array_map('intval', $validated['menu_item_ids'] ?? [])),
                'promo_code_ids' => array_values(array_map('intval', $validated['promo_code_ids'] ?? [])),
                'background_color' => $validated['background_color'] ?? '#FFFFFF',
                'background_opacity' => (float) ($validated['background_opacity'] ?? 0.88),
                'background_image' => $backgroundImagePath,
                'hero_media' => $heroMediaPath,
            ],
        ];
    }

    private function uploadHeroSectionMedia(?string $currentPath, bool $removeCurrent): ?string
    {
        if ($removeCurrent && is_string($currentPath) && $currentPath !== '') {
            $this->deletePublicFileIfExists($currentPath);
            $currentPath = null;
        }

        if (! request()->hasFile('hero_media')) {
            return $currentPath;
        }

        if (is_string($currentPath) && $currentPath !== '') {
            $this->deletePublicFileIfExists($currentPath);
        }

        return request()->file('hero_media')->store('home-sections/heroes', 'public');
    }

    private function deletePublicFileIfExists(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function formData(HomeSection $homeSection): array
    {
        return [
            'homeSection' => $homeSection,
            'types' => HomeSection::TYPES,
            'sources' => HomeSection::SOURCES,
            'restaurantScopes' => HomeSection::RESTAURANT_SCOPES,
            'banners' => Banner::query()->orderBy('display_order')->orderByDesc('id')->get(['id', 'title']),
            'restaurants' => Restaurant::query()->where('is_verified', true)->orderBy('name')->get(['id', 'name']),
            'cuisines' => Cuisine::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'globalCategories' => GlobalMenuCategory::query()
                ->active()
                ->parents()
                ->with(['children' => fn ($query) => $query->active()])
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name', 'display_order', 'is_active']),
            'menuItems' => MasterMenuItem::query()
                ->orderBy('category_name')
                ->orderBy('subcategory_name')
                ->orderBy('name')
                ->get(['id', 'name', 'category_name', 'subcategory_name', 'is_active']),
            'promoCodes' => PromoCode::query()->where('is_active', true)->whereNull('restaurant_id')->where('created_by_type', 'admin')->orderBy('code')->get(['id', 'code', 'title']),
        ];
    }
}
