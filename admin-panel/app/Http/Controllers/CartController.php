<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $restaurant = Restaurant::find($request->query('restaurant_id'));

        if (!$restaurant) {
            return redirect()->route('home')->with('error', 'Restaurant not found');
        }

        $appName = AppSetting::getValue('app_name', 'Food Delivery');
        $primaryColor = AppSetting::getValue('primary_color', '#2563EB');
        $primaryDark = '#1D4ED8';
        $currencySymbol = AppSetting::sanitizedCurrencySymbol();
        $currencyDecimals = AppSetting::currencyDecimals();
        $restaurantLogoUrl = $restaurant->logo_image
            ? \App\Services\MediaStorage::url($restaurant->logo_image)
            : null;

        return view('cart', compact(
            'restaurant', 'appName', 'primaryColor', 'primaryDark',
            'currencySymbol', 'currencyDecimals', 'restaurantLogoUrl'
        ));
    }
}
