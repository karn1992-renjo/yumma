<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\WebhookDelivery;
use App\Support\WebhookSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** total attempts before the delivery is parked as `dead` */
    public int $tries = 8;

    /** seconds between retries, indexed by attempt */
    private const BACKOFF = [30, 120, 300, 900, 1800, 3600, 7200];

    public function __construct(public int $deliveryId)
    {
    }

    public function backoff(): array
    {
        return self::BACKOFF;
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if (! $delivery || $delivery->status === 'sent') {
            return;
        }

        $base = rtrim((string) AppSetting::getValue("integration_{$delivery->target}_url", ''), '/');
        $secret = (string) AppSetting::getValue("integration_{$delivery->target}_secret", '');
        if ($base === '' || $secret === '') {
            $delivery->update(['status' => 'failed', 'last_error' => 'target not configured']);

            return;
        }

        $path = match (true) {
            str_starts_with($delivery->topic, 'user.') => '/api/ingest/user',
            str_starts_with($delivery->topic, 'journal.') => '/api/ingest/journal',
            default => '/api/ingest/ledger-event',
        };

        $body = json_encode([
            'event_id' => $delivery->event_id,
            'topic' => $delivery->topic,
            'data' => $delivery->payload,
        ], JSON_UNESCAPED_SLASHES);

        $delivery->increment('attempts');

        $res = Http::withHeaders(WebhookSignature::headers($body, $secret))
            ->withBody($body, 'application/json')
            ->timeout(15)
            ->post($base . $path);

        if ($res->successful()) {
            $delivery->update(['status' => 'sent', 'delivered_at' => now(), 'last_error' => null]);

            return;
        }

        $delivery->update(['status' => 'failed', 'last_error' => "HTTP {$res->status()}: " . mb_substr($res->body(), 0, 500)]);
        throw new \RuntimeException("webhook {$delivery->topic} → {$delivery->target} failed: HTTP {$res->status()}");
    }

    public function failed(\Throwable $e): void
    {
        WebhookDelivery::where('id', $this->deliveryId)->update([
            'status' => 'dead',
            'last_error' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }
}
