<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\VendorBankAccount;
use Illuminate\Http\Request;

class VendorBankController extends Controller
{
    public function showGeneric(Request $request, int $vendorId)
    {
        return $this->show($request->input('vendor_type', 'driver'), $vendorId);
    }

    public function storeGeneric(Request $request, int $vendorId)
    {
        return $this->store($request, $request->input('vendor_type', 'driver'), $vendorId);
    }

    public function show(string $vendorType, int $vendorId)
    {
        [$user, $vendorName] = $this->resolveVendor($vendorType, $vendorId);
        $accounts = VendorBankAccount::forVendor($vendorType, $vendorId)->latest()->get();

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'data' => $accounts]);
        }

        return view('admin.vendor.bank-details', compact('vendorType', 'vendorId', 'vendorName', 'user', 'accounts'));
    }

    public function store(Request $request, string $vendorType, int $vendorId)
    {
        [$user] = $this->resolveVendor($vendorType, $vendorId);
        $validated = $request->validate([
            'account_holder_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:64|required_without:upi_id',
            'ifsc_code' => 'nullable|string|max:32|required_with:account_number|required_without:routing_code',
            'routing_code' => 'nullable|string|max:32|required_with:account_number|required_without:ifsc_code',
            'upi_id' => 'nullable|string|max:255|required_without:account_number',
            'bank_name' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ]);

        if ($request->boolean('is_default')) {
            VendorBankAccount::forVendor($vendorType, $vendorId)->update(['is_default' => false]);
        }

        $account = VendorBankAccount::create([
            'vendor_type' => $vendorType,
            'vendor_id' => $vendorId,
            'user_id' => $user?->id,
            'account_holder_name' => $validated['account_holder_name'],
            'account_number_encrypted' => $validated['account_number'] ?? null,
            'account_number_last4' => !empty($validated['account_number']) ? substr($validated['account_number'], -4) : null,
            'ifsc_code_encrypted' => $validated['routing_code'] ?? $validated['ifsc_code'] ?? null,
            'upi_id_encrypted' => $validated['upi_id'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'is_default' => $request->boolean('is_default', true),
            'meta' => [
                'routing_code' => $validated['routing_code'] ?? $validated['ifsc_code'] ?? null,
            ],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $account]);
        }

        return redirect()->back()->with('success', 'Bank details saved.');
    }

    public function testTransfer(VendorBankAccount $bankAccount)
    {
        $bankAccount->update(['is_verified' => true, 'verified_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Bank details marked verified for payout testing.']);
    }

    private function resolveVendor(string $vendorType, int $vendorId): array
    {
        if ($vendorType === 'restaurant') {
            $restaurant = Restaurant::with('owner')->findOrFail($vendorId);
            return [$restaurant->owner, $restaurant->name];
        }

        $driver = User::findOrFail($vendorId);
        return [$driver, $driver->name];
    }
}
