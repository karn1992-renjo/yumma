<?php

namespace App\Services\Ai;

use App\Events\AiDecisionLoggedEvent;
use App\Models\AiDecision;
use Illuminate\Support\Facades\Log;

class AiActivityBroadcastService
{
    public function broadcast(AiDecision $decision): void
    {
        try {
            broadcast(new AiDecisionLoggedEvent($decision));
        } catch (\Throwable $exception) {
            Log::warning('AI activity broadcast failed.', ['error' => $exception->getMessage()]);
        }
    }
}
