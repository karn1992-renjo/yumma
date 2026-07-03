<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display list of user addresses
     */
    public function index()
    {
        $addresses = Auth::user()->addresses()->orderBy('is_default', 'desc')->orderBy('created_at', 'desc')->get();
        return view('customer.addresses.index', compact('addresses'));
    }

    /**
     * Store a new address
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'address' => 'required|string|max:1000',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'pincode' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $data['name'] = $data['name'] ?? 'Home';
        
        // If this is the first address, make it default
        if (Auth::user()->addresses()->count() == 0) {
            $data['is_default'] = true;
        } else {
            $data['is_default'] = false;
        }

        $address = Auth::user()->addresses()->create($data);

        if ($request->ajax() || $request->wantsJson() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Address saved successfully.',
                'address' => $address,
            ]);
        }

        return redirect()->route('customer.addresses.index')
            ->with('success', 'Address added successfully!');
    }

    /**
     * Update an address
     */
    public function update(Request $request, $id)
    {
        $address = Auth::user()->addresses()->findOrFail($id);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'address' => 'required|string|max:1000',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'pincode' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $data['name'] = $data['name'] ?? $address->name;

        $address->update($data);

        return redirect()->route('customer.addresses.index')
            ->with('success', 'Address updated successfully!');
    }

    /**
     * Delete an address
     */
    public function destroy($id)
    {
        $address = Auth::user()->addresses()->findOrFail($id);
        
        // Check if this is the default address
        $wasDefault = $address->is_default;
        
        $address->delete();
        
        // If deleted address was default, set another address as default
        if ($wasDefault) {
            $newDefault = Auth::user()->addresses()->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return redirect()->route('customer.addresses.index')
            ->with('success', 'Address deleted successfully!');
    }

    /**
     * Set an address as default
     */
    public function setDefault($id)
    {
        // Remove default from all addresses
        Auth::user()->addresses()->update(['is_default' => false]);
        
        // Set the selected address as default
        $address = Auth::user()->addresses()->findOrFail($id);
        $address->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default address updated!'
        ]);
    }
}
