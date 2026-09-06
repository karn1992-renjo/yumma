<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoiceAiSession;
use App\Services\AiVoiceTokenService;
use App\Services\VoiceAiAllowanceService;
use App\Services\VoiceAiSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VoiceAiConfigController extends Controller
{
    public function status(VoiceAiSettingsService $settings)
    {
        $config = $settings->settings();
        $serviceEnabled = (bool) $config['voice_ai_enabled'];

        return response()->json([
            'success' => true,
            'data' => [
                'service_enabled' => $serviceEnabled,
                'enabled' => $serviceEnabled,
            ],
        ]);
    }

    public function public(Request $request, VoiceAiSettingsService $settings, VoiceAiAllowanceService $allowance)
    {
        $remaining = $allowance->remainingSeconds($request->user());

        return response()->json([
            'success' => true,
            'data' => $settings->publicConfig($remaining),
        ]);
    }

    public function internal(Request $request, VoiceAiSettingsService $settings)
    {
        if (! $this->validServiceSecret($request)) {
            return response()->json(['success' => false, 'message' => 'AI service authentication failed.'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $settings->internalConfig(),
        ]);
    }

    public function usage(
        Request $request,
        AiVoiceTokenService $tokens,
        VoiceAiAllowanceService $allowance
    ) {
        if (! $this->validServiceSecret($request)) {
            return response()->json(['success' => false, 'message' => 'AI service authentication failed.'], 401);
        }

        $user = $tokens->resolveUser($request->header('X-AI-Voice-Token'));
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'AI voice token is invalid or expired.'], 401);
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'max:40'],
            'fallback_provider' => ['nullable', 'string', 'max:40'],
            'fallback_used' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:started,active,ended,failed'],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'input_audio_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'output_audio_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'tool_calls_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'failure_reason' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ]);

        $remaining = $allowance->consume($user, (int) ($validated['seconds'] ?? 0));
        $status = $validated['status'] ?? 'active';

        $session = VoiceAiSession::query()->firstOrNew(['session_id' => $validated['session_id']]);
        $session->fill([
            'user_id' => $user->id,
            'provider' => $validated['provider'] ?? $session->provider,
            'fallback_provider' => $validated['fallback_provider'] ?? $session->fallback_provider,
            'fallback_used' => (bool) ($validated['fallback_used'] ?? $session->fallback_used),
            'status' => $status,
            'failure_reason' => $validated['failure_reason'] ?? $session->failure_reason,
            'metadata' => $validated['metadata'] ?? $session->metadata,
        ]);

        if (! $session->started_at) {
            $session->started_at = Carbon::now();
        }
        if ($status === 'ended' || $status === 'failed') {
            $session->ended_at = Carbon::now();
        }
        if (isset($validated['seconds'])) {
            $session->active_seconds = (int) $session->active_seconds + (int) $validated['seconds'];
        }
        if (isset($validated['input_audio_seconds'])) {
            $session->input_audio_seconds = (int) $session->input_audio_seconds + (int) $validated['input_audio_seconds'];
        }
        if (isset($validated['output_audio_seconds'])) {
            $session->output_audio_seconds = (int) $session->output_audio_seconds + (int) $validated['output_audio_seconds'];
        }
        if (isset($validated['tool_calls_count'])) {
            $session->tool_calls_count = (int) $session->tool_calls_count + (int) $validated['tool_calls_count'];
        }
        if (isset($validated['order_id'])) {
            $session->order_id = $validated['order_id'];
        }
        $session->save();

        return response()->json([
            'success' => true,
            'data' => [
                'remaining_seconds' => $remaining,
                'session' => $session->fresh(),
            ],
        ]);
    }

    private function validServiceSecret(Request $request): bool
    {
        $configuredSecret = (string) config('services.ai.secret');
        $providedSecret = (string) $request->header('X-AI-Service-Secret', '');

        return $configuredSecret !== '' && hash_equals($configuredSecret, $providedSecret);
    }
}
