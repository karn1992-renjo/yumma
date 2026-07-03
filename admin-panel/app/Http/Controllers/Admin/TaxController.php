<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxSetting;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index()
    {
        $taxes = TaxSetting::all();
        return view('admin.taxes.index', compact('taxes'));
    }
    
    public function store(Request $request)
    {
        $request->merge(['calculation_type' => $this->normalizedCalculationType($request)]);

        $request->validate([
            'name' => 'required|string|max:255',
            'rate' => [
                'required',
                'numeric',
                'min:0',
                $request->input('calculation_type') === 'fixed' ? 'max:999.99' : 'max:100',
            ],
            'type' => 'required|in:gst,service_charge,packaging_charge,delivery_charge_tax',
            'calculation_type' => 'required|in:percentage,fixed',
            'description' => 'nullable|string',
        ]);

        TaxSetting::create($this->taxPayload($request));
        
        return redirect()->back()->with('success', 'Tax added successfully!');
    }
    
    public function update(Request $request, $id)
    {
        $tax = TaxSetting::findOrFail($id);
        $request->merge(['calculation_type' => $this->normalizedCalculationType($request)]);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'rate' => [
                'required',
                'numeric',
                'min:0',
                $request->input('calculation_type') === 'fixed' ? 'max:999.99' : 'max:100',
            ],
            'type' => 'required|in:gst,service_charge,packaging_charge,delivery_charge_tax',
            'calculation_type' => 'required|in:percentage,fixed',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $tax->update($this->taxPayload($request, true));
        
        return redirect()->back()->with('success', 'Tax updated successfully!');
    }
    
    public function destroy($id)
    {
        $tax = TaxSetting::findOrFail($id);
        $tax->delete();
        
        return redirect()->back()->with('success', 'Tax deleted successfully!');
    }

    private function taxPayload(Request $request, bool $includeStatus = false): array
    {
        $type = $request->input('type');
        $calculationType = $this->normalizedCalculationType($request);

        $payload = [
            'name' => $request->input('name'),
            'rate' => $request->input('rate'),
            'type' => $type,
            'calculation_type' => $calculationType,
            'description' => $request->input('description'),
        ];

        if ($includeStatus) {
            $payload['is_active'] = $request->boolean('is_active');
        }

        return $payload;
    }

    private function normalizedCalculationType(Request $request): string
    {
        $type = $request->input('type');
        $calculationType = $request->input('calculation_type');

        if (in_array($type, ['gst', 'delivery_charge_tax'], true)) {
            return 'percentage';
        }

        if ($calculationType === 'percentage' || $calculationType === 'fixed') {
            return $calculationType;
        }

        return $type === 'packaging_charge' ? 'fixed' : 'percentage';
    }
}
