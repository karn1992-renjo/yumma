<?php
// app/Http/Controllers/Admin/RefundPolicyController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundPolicy;
use Illuminate\Http\Request;

class RefundPolicyController extends Controller
{
    public function index()
    {
        $policies = RefundPolicy::orderBy('created_at', 'desc')->get();
        $activePolicy = RefundPolicy::where('status', 'active')->first();
        
        return view('admin.refund-policies.index', compact('policies', 'activePolicy'));
    }
    
    public function create()
    {
        return view('admin.refund-policies.create');
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'refund_window_hours' => 'required|integer|min:0|max:168',
            'delivery_charge_refund_percentage' => 'required|numeric|min:0|max:100',
            'cancellation_refund_rules' => 'nullable|array',
            'status' => 'required|in:active,inactive'
        ]);
        
        // If setting as active, deactivate others
        if ($request->status === 'active') {
            RefundPolicy::where('status', 'active')->update(['status' => 'inactive']);
        }
        
        $policy = RefundPolicy::create([
            'title' => $request->title,
            'content' => $request->content,
            'refund_window_hours' => $request->refund_window_hours,
            'delivery_charge_refund_percentage' => $request->delivery_charge_refund_percentage,
            'cancellation_refund_rules' => $request->cancellation_refund_rules,
            'status' => $request->status
        ]);
        
        return redirect()->route('admin.refund-policies.index')
            ->with('success', 'Refund policy created successfully!');
    }
    
    public function edit(RefundPolicy $refundPolicy)
    {
        return view('admin.refund-policies.edit', compact('refundPolicy'));
    }
    
    public function update(Request $request, RefundPolicy $refundPolicy)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'refund_window_hours' => 'required|integer|min:0|max:168',
            'delivery_charge_refund_percentage' => 'required|numeric|min:0|max:100',
            'cancellation_refund_rules' => 'nullable|array',
            'status' => 'required|in:active,inactive'
        ]);
        
        // If setting as active, deactivate others
        if ($request->status === 'active') {
            RefundPolicy::where('status', 'active')
                ->where('id', '!=', $refundPolicy->id)
                ->update(['status' => 'inactive']);
        }
        
        $refundPolicy->update([
            'title' => $request->title,
            'content' => $request->content,
            'refund_window_hours' => $request->refund_window_hours,
            'delivery_charge_refund_percentage' => $request->delivery_charge_refund_percentage,
            'cancellation_refund_rules' => $request->cancellation_refund_rules,
            'status' => $request->status
        ]);
        
        return redirect()->route('admin.refund-policies.index')
            ->with('success', 'Refund policy updated successfully!');
    }
    
    public function destroy(RefundPolicy $refundPolicy)
    {
        if ($refundPolicy->status === 'active') {
            return redirect()->back()->with('error', 'Cannot delete active policy. Please deactivate it first.');
        }
        
        $refundPolicy->delete();
        
        return redirect()->route('admin.refund-policies.index')
            ->with('success', 'Refund policy deleted successfully!');
    }
    
    public function setActive(RefundPolicy $refundPolicy)
    {
        RefundPolicy::where('status', 'active')->update(['status' => 'inactive']);
        $refundPolicy->update(['status' => 'active']);
        
        return redirect()->back()->with('success', 'Active policy updated successfully!');
    }
}
