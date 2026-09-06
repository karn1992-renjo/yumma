<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Builds the marketing artwork attached to an AI push notification.
 *
 * Pipeline (see App\Services\Ai\CampaignImageSpec):
 *   structured campaign  ->  AiCampaignImagePromptBuilder  ->  OpenAI gpt-image-1
 *   (photographic campaign visual, NO text/logo/UI)  ->  PHP composites the
 *   REAL stored app logo + an optional tiny promo badge  ->  1200x600 JPEG on
 *   the public disk  ->  URL handed to FCM.
 *
 * The image never reprints the notification title/message. Generation is
 * gated by ai_notification_images_enabled + a monthly USD cap and every paid
 * call is logged to ai_usage_logs. Any failure returns null so the caller
 * sends a text-only notification -- there is no placeholder banner.
 */
class AiNotificationImageService
{
    private const DIR = 'ai-notifications';

    private const W = 1200;

    private const H = 600;

    private const TTL_DAYS = 7;

    /** Flat per-image cost estimate for gpt-image-1 "high" at 1536x1024 (USD). */
    private const EST_COST_PER_IMAGE = 0.19;

    /** notification_type = VOICE/TONE only -- never a campaign_type. */
    private const NOTIFICATION_TONES = ['shayari', 'joke', 'human', 'wholesome', 'hype', 'deal'];

    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiCampaignImagePromptBuilder $promptBuilder,
    ) {}

    /**
     * Public URL of the campaign artwork for $spec, or null (caller then
     * sends text-only). With $allowGeneration = false only a cached image is
     * returned -- used by the synchronous send path so a cold generation is
     * pushed to App\Jobs\GenerateCampaignArtworkJob instead of blocking.
     */
    public function campaignImageUrl(CampaignImageSpec $spec, bool $allowGeneration = true): ?string
    {
        $brand = BrandContext::resolve();
        $badge = $this->normalizeBadge($spec->promoBadgeText);
        $finalRel = self::DIR . '/campaign-' . Str::slug($spec->campaignType ?: 'campaign') . '-' . $spec->cacheKey($brand) . '.jpg';

        MediaStorage::configure();
        $disk = Storage::disk('public');

        try {
            if ($disk->exists($finalRel) && $disk->lastModified($finalRel) > now()->subDays(self::TTL_DAYS)->timestamp) {
                return MediaStorage::url($finalRel);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (! $allowGeneration) {
            return null;
        }

        if (! $this->imagesEnabled() || ! $this->withinImageBudget()) {
            return null;
        }

        $artwork = $this->generateArtwork($spec, $brand);
        if ($artwork === null) {
            return null;
        }

        try {
            $jpeg = $this->composeFinal($artwork, $brand, $badge);
            $disk->put($finalRel, $jpeg, ['visibility' => 'public', 'CacheControl' => 'public, max-age=604800']);

            return MediaStorage::url($finalRel);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** True when this concept already has a fresh, ready-to-send image. */
    public function cachedImageUrl(CampaignImageSpec $spec): ?string
    {
        return $this->campaignImageUrl($spec, allowGeneration: false);
    }

    public function imagesEnabled(): bool
    {
        return $this->settings->bool('ai_notification_images_enabled', true);
    }

    /** Owner opted into async artwork generation (queue/CLI hands off instead of inline). */
    public function asyncEnabled(): bool
    {
        return $this->settings->bool('ai_image_async', false);
    }

    /** Month-to-date gpt-image-1 spend + one more call must stay within the cap (0 = unlimited). */
    public function withinImageBudget(): bool
    {
        $cap = (float) $this->settings->get('ai_image_monthly_budget_usd', 10);
        if ($cap <= 0) {
            return true;
        }

        try {
            $spent = (float) AiUsageLog::query()
                ->where('model', 'gpt-image-1')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('estimated_cost');
        } catch (\Throwable $e) {
            report($e);

            return true;
        }

        return ($spent + self::EST_COST_PER_IMAGE) <= $cap;
    }

    /**
     * OpenAI gpt-image-1 campaign visual as raw bytes (no logo/badge yet).
     * Raw output is cached per concept so re-compositing never re-pays.
     * Returns null on any failure; every real API call is logged.
     */
    private function generateArtwork(CampaignImageSpec $spec, BrandContext $brand): ?string
    {
        MediaStorage::configure();
        $disk = Storage::disk('public');
        $srcRel = self::DIR . '/src/art-' . $spec->artKey($brand) . '.png';

        try {
            if ($disk->exists($srcRel)) {
                return $disk->get($srcRel);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $key = $this->openAiKey();
        if ($key === '') {
            $this->logImageUsage($spec, 'failed', 0, 'openai_api_key missing');

            return null;
        }

        $prompt = $this->promptBuilder->build($spec, $brand);
        $started = microtime(true);

        try {
            $res = Http::withToken($key)
                ->timeout(75)
                ->acceptJson()
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => 'gpt-image-1',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1536x1024',
                    'quality' => 'high',
                    'background' => 'opaque',
                ]);
        } catch (\Throwable $e) {
            report($e);
            $this->logImageUsage($spec, 'failed', (int) ((microtime(true) - $started) * 1000), 'exception: ' . mb_substr($e->getMessage(), 0, 120));

            return null;
        }

        $latency = (int) ((microtime(true) - $started) * 1000);

        if (! $res->successful()) {
            report(new \RuntimeException('gpt-image-1 failed: ' . $res->status() . ' ' . mb_substr($res->body(), 0, 300)));
            $this->logImageUsage($spec, 'failed', $latency, 'http ' . $res->status());

            return null;
        }

        $b64 = data_get($res->json(), 'data.0.b64_json');
        $url = data_get($res->json(), 'data.0.url');
        $bytes = $b64 ? base64_decode($b64, true) : ($url ? $this->fetchUrl($url) : null);

        if ($bytes === null || $bytes === false || $bytes === '') {
            $this->logImageUsage($spec, 'failed', $latency, 'empty image payload');

            return null;
        }

        try {
            $disk->put($srcRel, $bytes, ['visibility' => 'public', 'CacheControl' => 'public, max-age=1209600']);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->logImageUsage($spec, 'success', $latency, null);

        return $bytes;
    }

    /**
     * Final artwork: cover to 1200x600, composite the REAL stored logo in the
     * top-left corner, add the optional promo badge, encode a size-bounded
     * JPEG. No campaign text is drawn.
     */
    private function composeFinal(string $artworkBytes, BrandContext $brand, ?string $badge): string
    {
        $manager = ImageManager::gd();
        $canvas = $manager->read($artworkBytes)->cover(self::W, self::H);

        // --- real app logo, top-left (never AI-generated) ---
        $logoBytes = $brand->logoBytes();
        if ($logoBytes !== null && $logoBytes !== '') {
            try {
                $logo = $manager->read($logoBytes)->scaleDown(width: 260, height: 120);
                $lw = $logo->width();
                $lh = $logo->height();
                $x = 48;
                $y = 44;
                // subtle bounded plate for legibility -- not a full panel
                $this->roundedRect($canvas, $x - 22, $y - 18, $lw + 44, $lh + 36, 22, 'rgba(0, 0, 0, 0.30)');
                $canvas->place($logo, 'top-left', $x, $y);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // --- optional tiny promo badge, bottom-left ---
        if ($badge !== null && ($font = $this->font()) !== null) {
            $chars = mb_strlen($badge);
            $bw = min(self::W - 96, (int) round($chars * 18 + 56));
            $bh = 58;
            $bx = 48;
            $by = self::H - 48 - $bh;
            $this->roundedRect($canvas, $bx, $by, $bw, $bh, 29, $brand->primaryColor);
            $canvas->text($badge, $bx + intdiv($bw, 2), $by + intdiv($bh, 2), function ($f) use ($font) {
                $f->filename($font);
                $f->size(26);
                $f->color('#ffffff');
                $f->align('center');
                $f->valign('middle');
            });
        }

        foreach ([82, 68, 55] as $quality) {
            $jpeg = (string) $canvas->toJpeg(quality: $quality)->toString();
            if (strlen($jpeg) <= 320 * 1024 || $quality === 55) {
                return $jpeg;
            }
        }

        return (string) $canvas->toJpeg(quality: 55)->toString();
    }

    private function logImageUsage(CampaignImageSpec $spec, string $status, int $latencyMs, ?string $error): void
    {
        try {
            $row = [
                'agent_key' => 'notification_image',
                'provider' => 'openai',
                'model' => 'gpt-image-1',
                'input_tokens' => 0,
                'output_tokens' => 0,
                'estimated_cost' => $status === 'success' ? self::EST_COST_PER_IMAGE : 0,
                'latency_ms' => $latencyMs,
                'status' => $status,
                'error' => $error,
            ];
            if (Schema::hasColumn('ai_usage_logs', 'context')) {
                $row['context'] = 'campaign:' . $spec->campaignType;
            }

            AiUsageLog::create($row);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** notification_type (voice/tone) normaliser -- unchanged contract. */
    public function normalizeType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, self::NOTIFICATION_TONES, true) ? $type : 'human';
    }

    /**
     * Sanitise a promo badge: uppercase, safe glyphs only, <= 20 chars.
     * Returns null when absent or invalid so no badge is drawn.
     */
    public function normalizeBadge(?string $text): ?string
    {
        $text = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $text)));
        if ($text === '') {
            return null;
        }
        if (! preg_match('/^[A-Z0-9 %₹$.+\/-]{1,20}$/u', $text)) {
            return null;
        }

        return $text;
    }

    private function openAiKey(): string
    {
        try {
            return trim((string) $this->settings->get('openai_api_key'));
        } catch (\Throwable) {
            return '';
        }
    }

    private function fetchUrl(string $url): ?string
    {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 8], 'https' => ['timeout' => 8]]);

            return @file_get_contents($url, false, $ctx) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function font(): ?string
    {
        $ttf = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        return is_file($ttf) ? $ttf : null;
    }

    /** Filled rounded rectangle (rect + 4 corner circles). */
    private function roundedRect($canvas, int $x, int $y, int $w, int $h, int $r, string $color): void
    {
        $r = max(0, min($r, intdiv($h, 2), intdiv($w, 2)));
        $canvas->drawRectangle($x + $r, $y, fn ($rect) => $rect->size($w - 2 * $r, $h)->background($color));
        $canvas->drawRectangle($x, $y + $r, fn ($rect) => $rect->size($w, $h - 2 * $r)->background($color));
        foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
            $canvas->drawCircle($cx, $cy, fn ($c) => $c->radius($r)->background($color));
        }
    }

    /**
     * @deprecated Kept only for backward compatibility. Prefer
     *             campaignImageUrl(CampaignImageSpec). Maps a bare tone to a
     *             safe campaign_type and generates synchronously.
     */
    public function bannerUrl(?string $type = null, ?int $restaurantId = null, ?string $headline = null, ?string $subline = null): ?string
    {
        $tone = $this->normalizeType($type);
        $campaignType = $tone === 'hype' ? 'discount' : 'food_recommendation';

        return $this->campaignImageUrl(new CampaignImageSpec($campaignType, null, null, null, $tone), true);
    }
}
