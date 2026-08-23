<?php

namespace App\Services;

use App\Events\GigOperationsUpdatedEvent;
use Illuminate\Support\Facades\Log;

class GigOperationsBroadcastService
{
    public function broadcast(?array $snapshot = null): void
    {
        try {
            $snapshot ??= app(GigOperationsService::class)->snapshot(today());
            broadcast(new GigOperationsUpdatedEvent($snapshot));
        } catch (\Throwable $exception) {
            Log::warning('Gig operations broadcast failed.', ['error' => $exception->getMessage()]);
        }
    }
}