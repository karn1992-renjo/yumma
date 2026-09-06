<?php

namespace App\Console\Commands;

use App\Models\PushBroadcast;
use App\Services\Ai\AiNotificationImageService;
use App\Services\Ai\CampaignImageSpec;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

/**
 * Safety net for AI push broadcasts whose artwork job never ran (no queue
 * worker, or the job failed silently). Runs every few minutes: finds
 * source=ai broadcasts still "pending" past a short grace period, generates
 * the campaign image if it is still wanted and not too old, then sends --
 * text-only if there is no image. Makes the campaign-image pipeline work even
 * with zero queue workers.
 */
class FlushStalePushBroadcasts extends Command
{
    protected $signature = 'ai:flush-stale-notifications
        {--now : ignore the grace period and flush every pending AI broadcast right now}
        {--limit=8 : maximum broadcasts to process this run}
        {--image-cutoff=25 : minutes after which stop trying to generate artwork and just send text-only}';

    protected $description = 'Deliver AI push broadcasts left pending because their artwork job was never processed.';

    public function handle(AiNotificationImageService $images, PushNotificationService $push): int
    {
        $grace = $this->option('now') ? 0 : 3;
        $cutoffMinutes = max(0, (int) $this->option('image-cutoff'));

        $rows = PushBroadcast::query()
            ->where('source', 'ai')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($grace))
            ->where('updated_at', '<=', now()->subMinutes($grace))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No stale AI broadcasts.');

            return self::SUCCESS;
        }

        foreach ($rows as $broadcast) {
            $broadcast->refresh();
            if ($broadcast->status !== 'pending') {
                continue; // a worker picked it up in the meantime
            }

            $payload = $broadcast->data_payload ?? [];
            $imageUrl = $payload['image_url'] ?? null;
            $wantsImage = $broadcast->image_status === 'queued'
                || (blank($broadcast->image_status) && filled($payload['campaign_image_concept'] ?? null));
            $tooOld = $broadcast->created_at?->lt(now()->subMinutes($cutoffMinutes)) ?? false;

            if ($wantsImage && blank($imageUrl) && ! $tooOld
                && $images->imagesEnabled() && $images->withinImageBudget()) {
                try {
                    $imageUrl = $images->campaignImageUrl(new CampaignImageSpec(
                        campaignType: (string) ($broadcast->campaign_type ?: ($payload['campaign_type'] ?? 'food_recommendation')),
                        campaignGoal: $payload['campaign_goal'] ?? null,
                        imageConcept: $payload['campaign_image_concept'] ?? null,
                        promoBadgeText: $payload['promo_badge_text'] ?? null,
                        notificationType: (string) ($broadcast->notification_type ?: 'human'),
                    ), allowGeneration: true);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $broadcast->forceFill([
                'image_status' => filled($imageUrl) ? 'ready' : ($wantsImage ? 'skipped' : $broadcast->image_status),
                'data_payload' => filled($imageUrl)
                    ? array_merge($payload, ['image_url' => $imageUrl, 'image' => $imageUrl])
                    : $payload,
            ])->save();

            $broadcast->refresh();
            if ($broadcast->status !== 'pending') {
                continue;
            }

            $push->sendBroadcast($broadcast);
            $this->line("#{$broadcast->id}  " . (filled($imageUrl) ? 'with image' : 'text-only') . '  -> ' . $broadcast->fresh()->status);
        }

        return self::SUCCESS;
    }
}
