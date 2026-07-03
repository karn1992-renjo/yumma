<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CelebrationType;
use Illuminate\Http\Request;

class CelebrationTypeController extends Controller
{
    public function index()
    {
        $celebrationTypes = CelebrationType::orderBy('display_order')->get();
        return view('admin.celebration-types.index', compact('celebrationTypes'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'display_order' => 'integer',
        ]);
        
        CelebrationType::create($request->all());
        
        return redirect()->back()->with('success', 'Celebration type added successfully!');
    }
    
    public function update(Request $request, $id)
    {
        $celebrationType = CelebrationType::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ]);
        
        $celebrationType->update($request->all());
        
        return redirect()->back()->with('success', 'Celebration type updated successfully!');
    }
    
    public function destroy($id)
    {
        $celebrationType = CelebrationType::findOrFail($id);
        $celebrationType->delete();
        
        return redirect()->back()->with('success', 'Celebration type deleted successfully!');
    }
}