<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SearchBannerController extends Controller
{
    public function index()
    {
        $banners = SearchBanner::orderBy('position')->get();
        return view('admin.search-banners.index', compact('banners'));
    }
    
    public function create()
    {
        return view('admin.search-banners.create');
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'required|file|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'link' => 'nullable|url',
            'position' => 'integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ]);
        
        if ($request->hasFile('image')) {
            // Support for GIF and other formats
            $path = $request->file('image')->store('search-banners', 'public');
            $validated['image'] = $path;
        }
        
        $validated['is_active'] = $request->has('is_active');
        
        SearchBanner::create($validated);
        
        return redirect()->route('admin.search-banners.index')
            ->with('success', 'Search banner created successfully!');
    }
    
    public function edit(SearchBanner $searchBanner)
    {
        return view('admin.search-banners.edit', compact('searchBanner'));
    }
    
    public function update(Request $request, SearchBanner $searchBanner)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'link' => 'nullable|url',
            'position' => 'integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ]);
        
        if ($request->hasFile('image')) {
            // Delete old image
            if ($searchBanner->image) {
                Storage::disk('public')->delete($searchBanner->image);
            }
            
            $path = $request->file('image')->store('search-banners', 'public');
            $validated['image'] = $path;
        }
        
        $validated['is_active'] = $request->has('is_active');
        
        $searchBanner->update($validated);
        
        return redirect()->route('admin.search-banners.index')
            ->with('success', 'Search banner updated successfully!');
    }
    
    public function destroy(SearchBanner $searchBanner)
    {
        if ($searchBanner->image) {
            Storage::disk('public')->delete($searchBanner->image);
        }
        
        $searchBanner->delete();
        
        return redirect()->route('admin.search-banners.index')
            ->with('success', 'Search banner deleted successfully!');
    }
}
