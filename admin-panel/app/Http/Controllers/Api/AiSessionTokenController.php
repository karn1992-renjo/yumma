<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiVoiceTokenService;
use App\Services\VoiceAiAllowanceService;
use App\Services\VoiceAiSettingsService;
use Illuminate\Http\Request;

class AiSessionTokenController extends Controller
{
    public function __invoke(
        Request $request,
        AiVoiceTokenService $tokens,
        VoiceAiSettingsService $settings,
        VoiceAiAllowanceService $allowance
    ) {
        $remaining = $allowance->remainingSeconds($request->user());
        $publicConfig = $settings->publicConfig($remaining);

        if (! $publicConfig['service_enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'AI voice assistant is disabled by admin.',
                'data' => $publicConfig,
            ], 403);
        }

        if (! $publicConfig['enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'Your AI voice allowance is finished. Place an order to recharge it.',
                'data' => $publicConfig,
            ], 429);
        }

        return response()->json([
            'success' => true,
            'data' => $publicConfig + [
                'voice_token' => $tokens->issue($request->user()),
                'expires_in' => 900,
            ],
        ]);
    }
}
