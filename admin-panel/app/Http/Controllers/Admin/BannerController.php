<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\HomeSectionService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BannerController extends Controller
{
    public function __construct(private readonly HomeSectionService $homeSectionService)
    {
    }

    public function index()
    {
        $banners = Banner::with(['redirectCategory', 'redirectRestaurant', 'redirectMenuItem'])->orderBy('display_order')->get();
        $bannerDurationSeconds = $this->bannerDurationSeconds();

        return view('admin.banners.index', compact('banners', 'bannerDurationSeconds'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'banner_duration_seconds' => 'required|integer|min:2|max:30',
        ]);

        AppSetting::updateOrCreate(
            ['key' => 'banner_duration_seconds'],
            ['value' => (string) $validated['banner_duration_seconds'], 'type' => 'number']
        );

        cache()->forget('app_settings');

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner duration updated successfully.');
    }
    
    public function create(Request $request)
    {
        return view('admin.banners.create', array_merge(
            $this->redirectOptions(),
            ['defaultLayoutMode' => $request->query('layout_mode')],
        ));
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cta_label' => 'nullable|string|max:40',
            'image' => ['required', 'file'],
            'badge_image' => ['nullable', 'image'],
            'link' => 'nullable|url',
            'redirect_type' => ['nullable', Rule::in(['category', 'restaurant', 'menu_item'])],
            'redirect_category_id' => ['nullable', 'required_if:redirect_type,category', 'exists:categories,id'],
            'redirect_restaurant_id' => ['nullable', 'required_if:redirect_type,restaurant', 'exists:restaurants,id'],
            'redirect_menu_item_id' => ['nullable', 'required_if:redirect_type,menu_item', 'exists:menu_items,id'],
            'display_order' => 'nullable|integer|min:0',
            'banner_type' => 'required|in:home,search_bar,category,promo',
            'layout_mode' => 'required|in:text_image,full_image,promo_card',
            'image_ratio' => 'nullable|integer|min:35|max:70',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->storeBannerMedia($request->file('image'));
        }

        if ($request->hasFile('badge_image')) {
            $validated['badge_image'] = $request->file('badge_image')->store('banners/badges', 'public');
        }
        
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['image_ratio'] = (int) ($validated['image_ratio'] ?? 46);
        $validated = $this->normalizeRedirectTarget($validated);

        $banner = Banner::create($validated);

        if ($this->shouldAttachToPromoWidgets($banner)) {
            $this->homeSectionService->attachPromoWidget($banner);

            return redirect()->route('admin.home-sections.index')
                ->with('success', 'Promo widget created and added to the "Promo Widgets" home section below.');
        }
        
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created successfully!');
    }
    
    public function edit(Banner $banner)
    {
        return view('admin.banners.edit', array_merge(compact('banner'), $this->redirectOptions()));
    }
    
    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cta_label' => 'nullable|string|max:40',
            'image' => ['nullable', 'file'],
            'badge_image' => ['nullable', 'image'],
            'remove_badge_image' => 'nullable|boolean',
            'link' => 'nullable|url',
            'redirect_type' => ['nullable', Rule::in(['category', 'restaurant', 'menu_item'])],
            'redirect_category_id' => ['nullable', 'required_if:redirect_type,category', 'exists:categories,id'],
            'redirect_restaurant_id' => ['nullable', 'required_if:redirect_type,restaurant', 'exists:restaurants,id'],
            'redirect_menu_item_id' => ['nullable', 'required_if:redirect_type,menu_item', 'exists:menu_items,id'],
            'display_order' => 'nullable|integer|min:0',
            'banner_type' => 'required|in:home,search_bar,category,promo',
            'layout_mode' => 'required|in:text_image,full_image,promo_card',
            'image_ratio' => 'nullable|integer|min:35|max:70',
            'is_active' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        if ($request->hasFile('image')) {
            $path = $this->storeBannerMedia($request->file('image'));
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $validated['image'] = $path;
        }

        if ($request->hasFile('badge_image')) {
            if ($banner->badge_image) {
                Storage::disk('public')->delete($banner->badge_image);
            }
            $validated['badge_image'] = $request->file('badge_image')->store('banners/badges', 'public');
        } elseif ($request->boolean('remove_badge_image')) {
            if ($banner->badge_image) {
                Storage::disk('public')->delete($banner->badge_image);
            }
            $validated['badge_image'] = null;
        }
        unset($validated['remove_badge_image']);
        
        $validated['is_active'] = $request->boolean('is_active');
        $validated['image_ratio'] = (int) ($validated['image_ratio'] ?? 46);
        $validated = $this->normalizeRedirectTarget($validated);

        $banner->update($validated);

        if ($this->shouldAttachToPromoWidgets($banner)) {
            $this->homeSectionService->attachPromoWidget($banner);

            return redirect()->route('admin.home-sections.index')
                ->with('success', 'Promo widget updated. Make sure the "Promo Widgets" home section below is shown on the homepage.');
        }

        $this->homeSectionService->detachPromoWidget($banner);
        
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated successfully!');
    }
    
    public function destroy(Banner $banner)
    {
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        if ($banner->badge_image) {
            Storage::disk('public')->delete($banner->badge_image);
        }

        $this->homeSectionService->detachPromoWidget($banner);
        $banner->delete();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner deleted successfully!');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'banners' => 'required|array',
            'banners.*.id' => 'required|exists:banners,id',
            'banners.*.order' => 'required|integer',
        ]);
        
        foreach ($request->banners as $bannerData) {
            Banner::where('id', $bannerData['id'])->update(['display_order' => $bannerData['order']]);
        }
        
        return response()->json(['success' => true]);
    }

    private function redirectOptions(): array
    {
        return [
            'categories' => Category::with('restaurant')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'restaurant_id', 'name']),
            'restaurants' => Restaurant::where('is_verified', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'menuItems' => MenuItem::with('restaurant')
                ->where('is_available', true)
                ->where(function ($query) {
                    $query->whereNull('approval_status')->orWhere('approval_status', 'approved');
                })
                ->orderBy('name')
                ->get(['id', 'restaurant_id', 'name']),
        ];
    }

    private function shouldAttachToPromoWidgets(Banner $banner): bool
    {
        return $banner->layout_mode === 'promo_card' || $banner->banner_type === 'promo';
    }
    private function normalizeRedirectTarget(array $validated): array
    {
        $type = $validated['redirect_type'] ?? null;

        $validated['redirect_category_id'] = $type === 'category' ? ($validated['redirect_category_id'] ?? null) : null;
        $validated['redirect_restaurant_id'] = $type === 'restaurant' ? ($validated['redirect_restaurant_id'] ?? null) : null;
        $validated['redirect_menu_item_id'] = $type === 'menu_item' ? ($validated['redirect_menu_item_id'] ?? null) : null;

        if (!$type) {
            $validated['redirect_type'] = null;
        }

        return $validated;
    }

    private function storeBannerMedia(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = strtolower((string) $file->getMimeType());
        $allowedImageMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $isJson = $extension === 'json'
            || in_array($mimeType, ['application/json', 'text/plain', 'text/json'], true);
        $isImage = in_array($mimeType, $allowedImageMimes, true)
            || in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true);

        if (! $isImage && ! $isJson) {
            throw ValidationException::withMessages([
                'image' => 'Please upload a banner image or a Lottie JSON file.',
            ]);
        }

        if ($isJson) {
            $contents = file_get_contents($file->getRealPath());
            json_decode($contents ?: '', true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'image' => 'The selected JSON file is not valid.',
                ]);
            }
        }



        return $file->store('banners', 'public');
    }

    private function bannerDurationSeconds(): int
    {
        return max(2, min(30, (int) AppSetting::getValue('banner_duration_seconds', 5)));
    }
}
