<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\CommissionSetting;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Cuisine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\MenuPriceAdjustmentService;

class RestaurantController extends Controller
{
    public function increaseMenuPrices(Request $request, Restaurant $restaurant, MenuPriceAdjustmentService $adjuster)
    {
        $data = $request->validate([
            'direction' => 'required|in:increase,decrease',
            'adjustment_type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|gt:0|max:1000000',
        ]);

        $count = $adjuster->adjust($restaurant, $data['direction'], $data['adjustment_type'], (float) $data['value']);

        return back()->with('success', "Updated prices for {$count} menu items.");
    }

    private function payoutProviderAccountAttributes(Request $request): array
    {
        $provider = \App\Models\AppSetting::getValue('payout_gateway_provider', 'razorpay');
        $gatewayAccountId = $request->gateway_account_id;

        return [
            'gateway_account_id' => $gatewayAccountId,
            'mollie_organization_id' => $provider === 'mollie' ? $gatewayAccountId : null,
            'mercadopago_collector_id' => $provider === 'mercadopago' ? $gatewayAccountId : null,
        ];
    }

    /**
     * Display a listing of restaurants with filters and statistics
     */
    public function index(Request $request)
    {
        $query = Restaurant::with(['owner', 'orders']);
        
        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        
        // Status filter (open/closed)
        if ($request->filled('status')) {
            $query->where('is_open', $request->status === 'active');
        }
        
        // Verification filter
        if ($request->filled('verification')) {
            $query->where('is_verified', $request->verification === 'verified');
        }
        
        // City filter
        if ($request->filled('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }
        
        $restaurants = $query->latest()->paginate(20)->withQueryString();
        
        // Statistics for dashboard
        $totalRestaurants = Restaurant::count();
        $activeRestaurants = Restaurant::where('is_open', true)->count();
        $pendingVerification = Restaurant::where('is_verified', false)->count();
        $totalOrders = \App\Models\Order::count();
        
        return view('admin.restaurants.index', compact(
            'restaurants', 
            'totalRestaurants', 
            'activeRestaurants', 
            'pendingVerification',
            'totalOrders'
        ));
    }
    
    /**
     * Show the form for creating a new restaurant
     */
    public function create()
    {
        $cuisines = Cuisine::where('is_active', true)->orderBy('display_order')->get();
        $deliveryAreas = DeliveryArea::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'area_type', 'description', 'latitude', 'longitude', 'radius_km', 'polygon_coordinates']);

        return view('admin.restaurants.create', compact('cuisines', 'deliveryAreas'));
    }
    
    /**
     * Store a newly created restaurant in storage
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Restaurant Information
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:restaurants,email',
            'phone' => 'required|string|max:20',
            'fssai_license_number' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'delivery_radius' => 'required|numeric|min:0|max:100',
            'restaurant_type' => 'required|in:' . implode(',', Restaurant::validServiceTypes()),
            'is_pure_veg' => 'nullable|boolean',
            'dining_charge' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'commission_calculation_type' => 'required|in:global,percentage,fixed',
            'commission_rate' => $request->commission_calculation_type === 'percentage'
                ? 'nullable|required_unless:commission_calculation_type,global|numeric|min:0|max:100'
                : 'nullable|required_unless:commission_calculation_type,global|numeric|min:0',
            'delivery_time' => 'nullable|integer|min:1|max:240',
            'order_lead_time' => 'nullable|integer|min:0|max:240',
            'open_time' => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string|timezone',
            'cuisine' => 'nullable|array',
            'cuisine.*' => 'string',
            'account_holder_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:64',
            'ifsc_code' => 'nullable|string|max:32',
            'routing_code' => 'nullable|string|max:32',
            'upi_id' => 'nullable|string|max:255',
            'stripe_account_id' => 'nullable|string|max:255',
            'gateway_account_id' => 'nullable|string|max:255',
            
            // Media
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            
            // Owner Information
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|unique:users,email',
            'owner_phone' => 'required|string|max:20|unique:users,phone',
            'owner_password' => 'required|string|min:8|confirmed',
        ]);
        
        try {
            // Create owner user
            $owner = User::create(array_merge([
                'name' => $request->owner_name,
                'email' => $request->owner_email,
                'phone' => $request->owner_phone,
                'password' => Hash::make($request->owner_password),
                'email_verified_at' => now(),
                'account_holder_name' => $request->account_holder_name,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'ifsc_code' => $request->routing_code ?: $request->ifsc_code,
                'routing_code' => $request->routing_code ?: $request->ifsc_code,
                'upi_id' => $request->upi_id,
                'stripe_account_id' => $request->stripe_account_id,
            ], $this->payoutProviderAccountAttributes($request)));
            
            // Assign role
            $owner->assignRole('restaurant_owner');
            
            // Generate unique slug
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;
            while (Restaurant::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }
            
            // Create restaurant
            $restaurant = Restaurant::create([
                'owner_id' => $owner->id,
                'name' => $request->name,
                'slug' => $slug,
                'email' => $request->email,
                'phone' => $request->phone,
                'fssai_license_number' => $request->fssai_license_number,
                'description' => $request->description,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'pincode' => $request->pincode,
                'latitude' => $request->latitude ?? 0,
                'longitude' => $request->longitude ?? 0,
                'delivery_radius' => $request->delivery_radius,
                'restaurant_type' => $request->restaurant_type,
                'is_pure_veg' => $request->boolean('is_pure_veg'),
                'dining_charge' => $request->dining_charge ?? 0,
                'min_order_amount' => $request->min_order_amount ?? 0,
                'delivery_fee' => $request->delivery_fee ?? 0,
                'commission_rate' => $request->commission_calculation_type === 'global' ? null : $request->commission_rate,
                'commission_calculation_type' => $request->commission_calculation_type,
                'delivery_time' => $request->delivery_time ?? 30,
                'order_lead_time' => $request->order_lead_time ?? 0,
                'open_time' => $request->open_time,
                'close_time' => $request->close_time,
                'weekly_timings' => $this->weeklyTimingsFromFlatHours(null, $request->open_time, $request->close_time),
                'timezone' => $request->timezone ?: 'Asia/Kolkata',
                'cuisine' => $request->cuisine ?? [],
                'is_open' => false,
                'is_verified' => false,
                'is_featured' => false,
            ]);
            
            // Handle logo upload
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('restaurants/logos', 'public');
                $restaurant->update(['logo_image' => $logoPath]);
            }
            
            // Handle banner upload
            if ($request->hasFile('banner')) {
                $bannerPath = $request->file('banner')->store('restaurants/banners', 'public');
                $restaurant->update(['banner_image' => $bannerPath]);
            }
            
            return redirect()->route('admin.restaurants.index')
                ->with('success', "Restaurant '{$restaurant->name}' created successfully! Owner credentials have been set.");
                
        } catch (\Exception $e) {
            \Log::error('Restaurant creation failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create restaurant. Please try again.');
        }
    }
    
    /**
     * Display the specified restaurant details
     */
    public function show(Restaurant $restaurant)
    {
        $restaurant->load(['owner', 'orders' => function($q) {
            $q->latest()->limit(10);
        }, 'menuItems' => function($q) {
            $q->with('category')->latest()->limit(10);
        }]);
        
        // Statistics
        $totalOrders = $restaurant->orders()->count();
        $totalRevenue = $restaurant->orders()->where('status', 'delivered')->sum('total');
        $averageRating = $restaurant->reviews()->avg('rating') ?? 0;
        $totalMenuItems = $restaurant->menuItems()->count();

        $financialSummary = (array) $restaurant->orders()
            ->where('status', 'delivered')
            ->selectRaw('COUNT(*) as delivered_orders')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as food_subtotal')
            ->selectRaw('COALESCE(SUM(total), 0) as customer_total')
            ->selectRaw('COALESCE(SUM(platform_fee), 0) as platform_charges')
            ->selectRaw('COALESCE(SUM(platform_commission), 0) as restaurant_commission')
            ->selectRaw('COALESCE(SUM(gst_on_commission), 0) as gst_on_commission')
            ->selectRaw('COALESCE(SUM(payment_gateway_fee), 0) as gateway_fees')
            ->selectRaw('COALESCE(SUM(restaurant_earning), 0) as restaurant_earning')
            ->selectRaw('COALESCE(SUM(branch_commission), 0) as branch_commission')
            ->selectRaw('COALESCE(SUM(admin_commission), 0) as admin_earning')
            ->first()
            ?->getAttributes();

        $financialSummary['released_earning'] = (float) $restaurant->orders()
            ->where('status', 'delivered')
            ->whereNotNull('restaurant_payout_id')
            ->sum('restaurant_earning');
        $financialSummary['pending_earning'] = (float) $restaurant->orders()
            ->where('status', 'delivered')
            ->whereNull('restaurant_payout_id')
            ->sum('restaurant_earning');

        $commissionType = $restaurant->commission_calculation_type;
        $commissionValue = $restaurant->commission_rate;
        if ($commissionType === 'global' || $commissionValue === null || $commissionValue === '') {
            $commissionType = CommissionSetting::getCalculationType(CommissionSetting::RESTAURANT);
            $commissionValue = CommissionSetting::getRate(CommissionSetting::RESTAURANT);
        }
        $commissionRule = [
            'source' => $restaurant->commission_calculation_type === 'global' || $restaurant->commission_rate === null
                ? 'Global setting'
                : 'Restaurant override',
            'type' => $commissionType ?: CommissionSetting::TYPE_PERCENTAGE,
            'value' => (float) $commissionValue,
        ];
        
        return view('admin.restaurants.show', compact(
            'restaurant', 
            'totalOrders', 
            'totalRevenue', 
            'averageRating',
            'totalMenuItems',
            'financialSummary',
            'commissionRule'
        ));
    }
    
    /**
     * Show the form for editing the specified restaurant
     */
    public function edit(Restaurant $restaurant)
    {
        $restaurant->load('owner');
        $cuisines = Cuisine::where('is_active', true)->orderBy('display_order')->get();
        $deliveryAreas = DeliveryArea::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'area_type', 'description', 'latitude', 'longitude', 'radius_km', 'polygon_coordinates']);

        return view('admin.restaurants.edit', compact('restaurant', 'cuisines', 'deliveryAreas'));
    }

    public function downloadTemplate()
    {
        $filename = 'restaurant-bulk-upload-template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = [
            'Restaurant Name',
            'Restaurant Email',
            'Restaurant Phone',
            'Description',
            'Address',
            'City',
            'State',
            'Pincode',
            'Latitude',
            'Longitude',
            'Delivery Radius',
            'Restaurant Type',
            'Pure Veg',
            'Min Order Amount',
            'Delivery Fee',
            'Cuisine',
            'Owner Name',
            'Owner Email',
            'Owner Phone',
            'Owner Password',
            'Verified',
            'Open',
        ];

        $sampleRow = [
            'Tandoori Treats',
            'tandoori@example.com',
            '+919876543210',
            'North Indian and tandoori specials',
            '12 MG Road',
            'Bengaluru',
            'Karnataka',
            '560001',
            '12.971599',
            '77.594566',
            '8',
            'delivery',
            'No',
            '150',
            '30',
            'North Indian, Biryani',
            'Ravi Kumar',
            'ravi.owner@example.com',
            '+919812345678',
            'Password@123',
            'Yes',
            'Yes',
        ];

        return response()->stream(function () use ($columns, $sampleRow) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, $sampleRow);
            fclose($handle);
        }, 200, $headers);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'upload_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $rows = $this->readUploadedRows($request->file('upload_file'));
        if (empty($rows)) {
            return back()->with('error', 'Uploaded file is empty or incorrectly formatted.');
        }

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $record) {
            $rowNumber = $index + 2;
            $record = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $record);

            $validator = Validator::make($record, [
                'restaurant_name' => 'required|string|max:255',
                'restaurant_email' => 'required|email|unique:restaurants,email',
                'restaurant_phone' => 'required|string|max:20',
                'description' => 'nullable|string',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'pincode' => 'required|string|max:10',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'delivery_radius' => 'nullable|numeric|min:0|max:100',
                'restaurant_type' => 'nullable|in:' . implode(',', Restaurant::validServiceTypes()),
                'pure_veg' => 'nullable|string',
                'min_order_amount' => 'nullable|numeric|min:0',
                'delivery_fee' => 'nullable|numeric|min:0',
                'cuisine' => 'nullable|string|max:1000',
                'owner_name' => 'required|string|max:255',
                'owner_email' => 'required|email|unique:users,email',
                'owner_phone' => 'required|string|max:20|unique:users,phone',
                'owner_password' => 'required|string|min:8',
                'verified' => 'nullable|string',
                'open' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            $owner = User::create([
                'name' => $record['owner_name'],
                'email' => $record['owner_email'],
                'phone' => $record['owner_phone'],
                'password' => Hash::make($record['owner_password']),
                'email_verified_at' => now(),
            ]);
            $owner->assignRole('restaurant_owner');

            $slug = Str::slug($record['restaurant_name']);
            $baseSlug = $slug;
            $counter = 1;
            while (Restaurant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            Restaurant::create([
                'owner_id' => $owner->id,
                'name' => $record['restaurant_name'],
                'slug' => $slug,
                'email' => $record['restaurant_email'],
                'phone' => $record['restaurant_phone'],
                'description' => $record['description'] ?? null,
                'address' => $record['address'],
                'city' => $record['city'],
                'state' => $record['state'],
                'pincode' => $record['pincode'],
                'latitude' => ($record['latitude'] ?? '') !== '' ? (float) $record['latitude'] : 0,
                'longitude' => ($record['longitude'] ?? '') !== '' ? (float) $record['longitude'] : 0,
                'delivery_radius' => ($record['delivery_radius'] ?? '') !== '' ? (float) $record['delivery_radius'] : 5,
                'restaurant_type' => ($record['restaurant_type'] ?? '') ?: 'delivery',
                'is_pure_veg' => $this->truthy($record['pure_veg'] ?? false),
                'min_order_amount' => ($record['min_order_amount'] ?? '') !== '' ? (float) $record['min_order_amount'] : 0,
                'delivery_fee' => ($record['delivery_fee'] ?? '') !== '' ? (float) $record['delivery_fee'] : 0,
                'cuisine' => $this->parseList($record['cuisine'] ?? ''),
                'is_verified' => $this->truthy($record['verified'] ?? true),
                'is_open' => $this->truthy($record['open'] ?? true),
                'is_featured' => false,
            ]);

            $created++;
        }

        $redirect = redirect()->route('admin.restaurants.index')
            ->with('success', "Imported {$created} restaurant" . ($created === 1 ? '' : 's') . ".");

        return empty($errors) ? $redirect : $redirect->with('upload_errors', $errors);
    }
    
    /**
     * Update the specified restaurant in storage
     */
    public function update(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:restaurants,email,' . $restaurant->id,
            'phone' => 'required|string|max:20',
            'fssai_license_number' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'delivery_radius' => 'required|numeric|min:0|max:100',
            'restaurant_type' => 'required|in:' . implode(',', Restaurant::validServiceTypes()),
            'is_pure_veg' => 'nullable|boolean',
            'dining_charge' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'commission_calculation_type' => 'required|in:global,percentage,fixed',
            'commission_rate' => $request->commission_calculation_type === 'percentage'
                ? 'nullable|required_unless:commission_calculation_type,global|numeric|min:0|max:100'
                : 'nullable|required_unless:commission_calculation_type,global|numeric|min:0',
            'delivery_time' => 'nullable|integer|min:1|max:240',
            'order_lead_time' => 'nullable|integer|min:0|max:240',
            'open_time' => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string|timezone',
            'cuisine' => 'nullable|array',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'is_open' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'account_holder_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:64',
            'ifsc_code' => 'nullable|string|max:32',
            'routing_code' => 'nullable|string|max:32',
            'upi_id' => 'nullable|string|max:255',
            'stripe_account_id' => 'nullable|string|max:255',
            'gateway_account_id' => 'nullable|string|max:255',
        ]);
        
        try {
            // Update slug if name changed
            if ($restaurant->name !== $request->name) {
                $slug = Str::slug($request->name);
                $originalSlug = $slug;
                $counter = 1;
                while (Restaurant::where('slug', $slug)->where('id', '!=', $restaurant->id)->exists()) {
                    $slug = $originalSlug . '-' . $counter++;
                }
                $validated['slug'] = $slug;
            }
            
            // Update restaurant
            $restaurant->update(array_merge($validated, [
                'restaurant_type' => $request->restaurant_type,
                'dining_charge' => $request->dining_charge ?? 0,
                'commission_rate' => $request->commission_calculation_type === 'global' ? null : $request->commission_rate,
                'commission_calculation_type' => $request->commission_calculation_type,
                'is_pure_veg' => $request->boolean('is_pure_veg'),
                'latitude' => $request->latitude ?? 0,
                'longitude' => $request->longitude ?? 0,
                'weekly_timings' => $this->weeklyTimingsFromFlatHours(
                    $restaurant->weekly_timings,
                    $request->open_time,
                    $request->close_time
                ),
            ]));

            if ($restaurant->owner) {
                $restaurant->owner->update($request->only([
                    'account_holder_name',
                    'bank_name',
                    'account_number',
                    'upi_id',
                    'stripe_account_id',
                ]));
                $restaurant->owner->update([
                    'ifsc_code' => $request->routing_code ?: $request->ifsc_code,
                    'routing_code' => $request->routing_code ?: $request->ifsc_code,
                ]);
                $restaurant->owner->update($this->payoutProviderAccountAttributes($request));
            }
            
            // Handle logo upload
            if ($request->hasFile('logo')) {
                if ($restaurant->logo_image) {
                    Storage::disk('public')->delete($restaurant->logo_image);
                }
                $logoPath = $request->file('logo')->store('restaurants/logos', 'public');
                $restaurant->update(['logo_image' => $logoPath]);
            }
            
            // Handle banner upload
            if ($request->hasFile('banner')) {
                if ($restaurant->banner_image) {
                    Storage::disk('public')->delete($restaurant->banner_image);
                }
                $bannerPath = $request->file('banner')->store('restaurants/banners', 'public');
                $restaurant->update(['banner_image' => $bannerPath]);
            }
            
            return redirect()->route('admin.restaurants.index')
                ->with('success', "Restaurant '{$restaurant->name}' updated successfully!");
                
        } catch (\Exception $e) {
            \Log::error('Restaurant update failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to update restaurant. Please try again.');
        }
    }
    
    /**
     * Remove the specified restaurant from storage
     */
    public function destroy(Restaurant $restaurant)
    {
        try {
            // Store restaurant name for flash message
            $restaurantName = $restaurant->name;
            
            // Delete images
            if ($restaurant->logo_image) {
                Storage::disk('public')->delete($restaurant->logo_image);
            }
            if ($restaurant->banner_image) {
                Storage::disk('public')->delete($restaurant->banner_image);
            }
            
            // Delete restaurant (this will cascade delete related data if set up)
            $restaurant->delete();
            
            return redirect()->route('admin.restaurants.index')
                ->with('success', "Restaurant '{$restaurantName}' deleted successfully!");
                
        } catch (\Exception $e) {
            \Log::error('Restaurant deletion failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete restaurant. Please try again.');
        }
    }
    
    /**
     * Toggle restaurant open/close status via AJAX
     */
    public function toggleStatus(Restaurant $restaurant)
    {
        try {
            $restaurant->update(['is_open' => !$restaurant->is_open]);
            
            return response()->json([
                'success' => true,
                'is_open' => $restaurant->is_open,
                'message' => $restaurant->is_open ? 'Restaurant is now open' : 'Restaurant is now closed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }
    
    /**
     * Bulk delete restaurants
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'restaurant_ids' => 'required|array',
            'restaurant_ids.*' => 'exists:restaurants,id'
        ]);
        
        try {
            $restaurants = Restaurant::whereIn('id', $request->restaurant_ids)->get();
            $count = $restaurants->count();
            
            foreach ($restaurants as $restaurant) {
                if ($restaurant->logo_image) {
                    Storage::disk('public')->delete($restaurant->logo_image);
                }
                if ($restaurant->banner_image) {
                    Storage::disk('public')->delete($restaurant->banner_image);
                }
                $restaurant->delete();
            }
            
            return redirect()->route('admin.restaurants.index')
                ->with('success', "{$count} restaurants deleted successfully!");
                
        } catch (\Exception $e) {
            \Log::error('Bulk delete failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete selected restaurants.');
        }
    }
    
    /**
     * Export restaurants to CSV
     */
    public function export(Request $request)
    {
        $query = Restaurant::with('owner');
        
        if ($request->filled('status')) {
            $query->where('is_open', $request->status === 'active');
        }
        
        if ($request->filled('verification')) {
            $query->where('is_verified', $request->verification === 'verified');
        }
        
        $restaurants = $query->get();
        
        $filename = 'restaurants-' . date('Y-m-d-His') . '.csv';
        $handle = fopen('php://temp', 'w+');
        
        // CSV Headers
        fputcsv($handle, [
            'ID', 'Name', 'Email', 'Phone', 'Owner Name', 'Owner Email', 
            'City', 'State', 'Pincode', 'Status', 'Verification', 
            'Orders Count', 'Created At'
        ]);
        
        foreach ($restaurants as $restaurant) {
            fputcsv($handle, [
                $restaurant->id,
                $restaurant->name,
                $restaurant->email,
                $restaurant->phone,
                $restaurant->owner->name ?? 'N/A',
                $restaurant->owner->email ?? 'N/A',
                $restaurant->city,
                $restaurant->state,
                $restaurant->pincode,
                $restaurant->is_open ? 'Open' : 'Closed',
                $restaurant->is_verified ? 'Verified' : 'Pending',
                $restaurant->orders()->count(),
                $restaurant->created_at->format('Y-m-d H:i:s')
            ]);
        }
        
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);
        
        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function weeklyTimingsFromFlatHours(?array $existing, ?string $openTime, ?string $closeTime): array
    {
        $timings = $existing ?: Restaurant::getDefaultWeeklyTimings();

        if (!$openTime && !$closeTime) {
            return $timings;
        }

        foreach ($timings as $day => $timing) {
            $timings[$day] = array_merge(Restaurant::getDefaultDayTiming(), $timing, [
                'open_time' => $openTime ?: ($timing['open_time'] ?? '09:00'),
                'close_time' => $closeTime ?: ($timing['close_time'] ?? '22:00'),
            ]);
        }

        return $timings;
    }

    private function readUploadedRows($file): array
    {
        $sheet = IOFactory::load($file->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, false);
        $header = array_shift($sheet);

        if (!$header) {
            return [];
        }

        $header = array_map(fn ($column) => Str::of((string) $column)->trim()->lower()->replace([' ', '-'], '_')->toString(), $header);
        $rows = [];

        foreach ($sheet as $row) {
            if (empty(array_filter($row, fn ($value) => $value !== null && $value !== ''))) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($row, count($header), null));
        }

        return $rows;
    }

    private function truthy($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'y', 'on', 'open', 'verified'], true);
    }

    private function parseList(?string $value): array
    {
        return collect(preg_split('/[,|;]/', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
    
    /**
     * Get restaurant statistics for dashboard API
     */
    public function statistics()
    {
        $stats = [
            'total' => Restaurant::count(),
            'active' => Restaurant::where('is_open', true)->count(),
            'verified' => Restaurant::where('is_verified', true)->count(),
            'featured' => Restaurant::where('is_featured', true)->count(),
            'new_this_month' => Restaurant::whereMonth('created_at', now()->month)->count(),
            'avg_rating' => round(Restaurant::avg('rating') ?? 0, 1),
        ];
        
        // Top restaurants by orders
        $topRestaurants = Restaurant::withCount('orders')
            ->orderBy('orders_count', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'orders_count']);
        
        return response()->json([
            'statistics' => $stats,
            'top_restaurants' => $topRestaurants
        ]);
    }
}
