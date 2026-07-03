<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }
    
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        
        $validated = $request->validated();
        
        if ($request->hasFile('profile_image')) {
            $request->validate([
                'profile_image' => 'image|mimes:jpeg,png,jpg|max:2048'
            ]);
            
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            
            $path = $request->file('profile_image')->store('profiles', 'public');
            $validated['profile_image'] = $path;
        }
        
        $user->fill($validated);
        
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        
        $user->save();
        
        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully!');
    }
    
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);
        
        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);
        
        return redirect()->route('profile.edit')->with('success', 'Password updated successfully!');
    }
    
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);
        
        $user = $request->user();
        
        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }
        
        Auth::logout();
        $user->delete();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/')->with('success', 'Account deleted successfully!');
    }
}