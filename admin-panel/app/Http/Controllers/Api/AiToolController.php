<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiToolService;
use App\Services\AiVoiceTokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiToolController extends Controller
{
    public function __construct(
        private readonly AiToolService $tools,
        private readonly AiVoiceTokenService $voiceTokens,
    ) {
    }

    public function __invoke(Request $request, string $tool)
    {
        $configuredSecret = (string) config('services.ai.secret');
        $providedSecret = (string) $request->header('X-AI-Service-Secret', '');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'AI service authentication failed.',
            ], 401);
        }

        $user = $this->voiceTokens->resolveUser($request->header('X-AI-Voice-Token'));
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'AI voice token is invalid or expired.',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        try {
            $arguments = $request->input('arguments', []);
            if (! is_array($arguments)) {
                throw ValidationException::withMessages(['arguments' => 'Arguments must be an object.']);
            }

            return response()->json([
                'success' => true,
                'data' => $this->tools->call($tool, $arguments, $request),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }
}