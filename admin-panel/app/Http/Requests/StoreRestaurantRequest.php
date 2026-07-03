<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'pincode' => 'required|string|max:10',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'phone' => 'required|string|max:20',
            'email' => 'required|email',
            'cuisine' => 'nullable|array',
            'min_order_amount' => 'nullable|integer|min:0',
            'delivery_fee' => 'nullable|integer|min:0',
            'delivery_time' => 'nullable|integer|min:10|max:120',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
    
    public function messages(): array
    {
        return [
            'name.required' => 'Restaurant name is required',
            'address.required' => 'Restaurant address is required',
            'latitude.required' => 'Please select restaurant location on map',
            'longitude.required' => 'Please select restaurant location on map',
        ];
    }
}