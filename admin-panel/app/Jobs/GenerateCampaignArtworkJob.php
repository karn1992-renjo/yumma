<?php

namespace App\Jobs;

use App\Models\PushBroadcast;
use App\Services\Ai\AiNotificationImageService;
use App\Services\Ai\CampaignImageSpec;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates the campaign artwork for a pending PushBroadcast off the request /
 * cron path, then sends the broadcast. If artwork generation fails, times out
 * or is skipped, the broadcast is still sent -- text-only. A slow OpenAI image
 * call can never block the AI management cycle or the notification itself.
 */
class GenerateCampaignArtworkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 150;

    public int $backoff = 30;

    /**
     * @param  array<string,mixed>  $spec  CampaignImageSpec::toArray()
     */
    public function __construct(
        public readonly int $broadcastId,
        public readonly array $spec,
    ) {}

    public function handle(AiNotificationImageService $images, PushNotificationService $push): void
    {
        $broadcast = PushBroadcast::find($this->broadcastId);
        if (! $broadcast || $broadcast->status !== 'pending') {
            return; // already sent / failed / gone
        }

        $imageUrl = null;
        try {
            $imageUrl = $images->campaignImageUrl(CampaignImageSpec::fromArray($this->spec), allowGeneration: true);
        } catch (\Throwable $e) {
            report($e);
        }

        if (filled($imageUrl)) {
            $broadcast->forceFill([
                'image_status' => 'ready',
                'data_payload' => array_merge($broadcast->data_payload ?? [], [
                    'image_url' => $imageUrl,
                    'image' => $imageUrl,
                ]),
            ])->save();
        } else {
            $broadcast->forceFill(['image_status' => 'skipped'])->save();
        }

        // Re-check: the flush command may have sent it while we were generating.
        $broadcast->refresh();
        if ($broadcast->status === 'pending') {
            $push->sendBroadcast($broadcast);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('GenerateCampaignArtworkJob failed, sending text-only', [
            'broadcast_id' => $this->broadcastId,
            'error' => $e->getMessage(),
        ]);

        $broadcast = PushBroadcast::find($this->broadcastId);
        if ($broadcast && $broadcast->status === 'pending') {
            $broadcast->forceFill(['image_status' => 'failed'])->save();
            app(PushNotificationService::class)->sendBroadcast($broadcast->fresh());
        }
    }
}
