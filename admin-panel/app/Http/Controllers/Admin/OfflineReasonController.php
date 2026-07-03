<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfflineReason;
use Illuminate\Http\Request;

class OfflineReasonController extends Controller
{
    public function index()
    {
        $reasons = OfflineReason::orderBy('created_at', 'desc')->get();
        return view('admin.offline-reasons.index', compact('reasons'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'sub_reasons' => 'nullable|array',
        ]);
        
        OfflineReason::create($request->all());
        
        return redirect()->back()->with('success', 'Reason added successfully!');
    }
    
    public function update(Request $request, $id)
    {
        $reason = OfflineReason::findOrFail($id);
        
        $request->validate([
            'reason' => 'required|string|max:255',
            'sub_reasons' => 'nullable|array',
            'is_active' => 'boolean',
        ]);
        
        $reason->update($request->all());
        
        return redirect()->back()->with('success', 'Reason updated successfully!');
    }
    
    public function destroy($id)
    {
        $reason = OfflineReason::findOrFail($id);
        $reason->delete();
        
        return redirect()->back()->with('success', 'Reason deleted successfully!');
    }
}