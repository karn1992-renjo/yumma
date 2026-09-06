<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\VoiceAiSettingsService;
use Illuminate\Http\Request;

class VoiceAiSettingsController extends Controller
{
    public function edit(VoiceAiSettingsService $settings)
    {
        return view('admin.settings.voice-ai', [
            'settings' => $settings->settings(),
            'health' => $settings->health(),
        ]);
    }

    public function update(Request $request, VoiceAiSettingsService $settings)
    {
        $validated = $request->validate([
            'voice_ai_enabled' => ['required', 'in:0,1'],
            'voice_ai_primary_provider' => ['required', 'in:gemini_live,self_hosted'],
            'voice_ai_fallback_provider' => ['required', 'in:none,gemini_live,self_hosted'],
            'voice_ai_max_session_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'voice_ai_idle_timeout_seconds' => ['required', 'integer', 'min:10', 'max:300'],
            'voice_ai_barge_in_enabled' => ['required', 'in:0,1'],
            'voice_ai_language' => ['required', 'in:auto,en,hi'],
            'voice_ai_allowance_enabled' => ['required', 'in:0,1'],
            'voice_ai_initial_allowance_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'voice_ai_recharge_after_order' => ['required', 'in:0,1'],
            'voice_ai_recharge_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'voice_ai_recharge_mode' => ['required', 'in:reset,add'],
            'voice_ai_gemini_model' => ['required', 'string', 'max:120'],
            'voice_ai_gemini_voice' => ['nullable', 'string', 'max:60'],
            'voice_ai_gemini_system_prompt' => ['required', 'string', 'max:5000'],
            'voice_ai_gemini_temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'voice_ai_gemini_tool_calling_enabled' => ['required', 'in:0,1'],
            'gemini_api_key' => ['nullable', 'string', 'max:1000'],
            'voice_ai_self_hosted_url' => ['required', 'url', 'max:500'],
            'voice_ai_self_hosted_ws_url' => ['nullable', 'string', 'max:500'],
            'self_hosted_api_secret' => ['nullable', 'string', 'max:1000'],
            'voice_ai_self_hosted_llm_model' => ['nullable', 'string', 'max:160'],
        ]);

        $settings->update($validated);

        return redirect()->route('admin.settings.voice-ai')
            ->with('success', 'Voice AI settings updated successfully.');
    }
}
