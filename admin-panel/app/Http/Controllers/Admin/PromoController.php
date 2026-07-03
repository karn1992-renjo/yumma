<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PromoController extends Controller
{
    public function index()
    {
        $promos = PromoCode::whereNull('restaurant_id')
            ->where('created_by_type', 'admin')
            ->latest()
            ->get();

        return view('admin.promos.index', compact('promos'));
    }

    public function create()
    {
        return view('admin.promos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'promo_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'audience_type' => 'required|in:all,new_customer,returning_customer',
            'coupon_type' => 'required|in:public,prepaid',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('promo_image')) {
            $validated['promo_image'] = $request->file('promo_image')->store('promos', 'public');
        }

        $validated['restaurant_id'] = null;
        $validated['created_by_type'] = 'admin';
        $validated['is_active'] = $request->boolean('is_active', true);

        PromoCode::create($validated);

        return redirect()->route('admin.promos.index')
            ->with('success', 'Admin promo created successfully.');
    }

    public function edit(PromoCode $promo)
    {
        abort_if($promo->restaurant_id !== null || $promo->created_by_type !== 'admin', 404);

        return view('admin.promos.edit', compact('promo'));
    }

    public function update(Request $request, PromoCode $promo)
    {
        abort_if($promo->restaurant_id !== null || $promo->created_by_type !== 'admin', 404);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code,' . $promo->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'promo_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'audience_type' => 'required|in:all,new_customer,returning_customer',
            'coupon_type' => 'required|in:public,prepaid',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('promo_image')) {
            if ($promo->promo_image) {
                Storage::disk('public')->delete($promo->promo_image);
            }
            $validated['promo_image'] = $request->file('promo_image')->store('promos', 'public');
        }

        $validated['restaurant_id'] = null;
        $validated['created_by_type'] = 'admin';
        $validated['is_active'] = $request->boolean('is_active');

        $promo->update($validated);

        return redirect()->route('admin.promos.index')
            ->with('success', 'Admin promo updated successfully.');
    }

    public function destroy(PromoCode $promo)
    {
        abort_if($promo->restaurant_id !== null || $promo->created_by_type !== 'admin', 404);

        if ($promo->promo_image) {
            Storage::disk('public')->delete($promo->promo_image);
        }

        $promo->delete();

        return redirect()->route('admin.promos.index')
            ->with('success', 'Admin promo deleted successfully.');
    }

    public function toggle(PromoCode $promo)
    {
        abort_if($promo->restaurant_id !== null || $promo->created_by_type !== 'admin', 404);

        $promo->update(['is_active' => !$promo->is_active]);

        return back()->with('success', 'Promo status updated.');
    }
}
