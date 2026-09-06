<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * The structured, validated description of ONE campaign's artwork -- the
 * hand-off object between App\Services\Ai\AiToolRegistry::sendNotification(),
 * App\Jobs\GenerateCampaignArtworkJob and
 * App\Services\Ai\AiNotificationImageService.
 *
 * It deliberately carries NO push title/message: the image represents the
 * campaign, it never reprints the notification copy.
 */
class CampaignImageSpec
{
    public function __construct(
        public readonly string $campaignType,
        public readonly ?string $campaignGoal = null,
        public readonly ?string $imageConcept = null,
        public readonly ?string $promoBadgeText = null,
        public readonly string $notificationType = 'human',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            campaignType: (string) ($data['campaign_type'] ?? 'food_recommendation'),
            campaignGoal: self::clean($data['campaign_goal'] ?? null, 60),
            imageConcept: self::clean($data['image_concept'] ?? null, 300),
            promoBadgeText: self::clean($data['promo_badge_text'] ?? null, 24),
            notificationType: (string) ($data['notification_type'] ?? 'human'),
        );
    }

    public function toArray(): array
    {
        return [
            'campaign_type' => $this->campaignType,
            'campaign_goal' => $this->campaignGoal,
            'image_concept' => $this->imageConcept,
            'promo_badge_text' => $this->promoBadgeText,
            'notification_type' => $this->notificationType,
        ];
    }

    /**
     * Key for the RAW OpenAI artwork (before logo/badge). Depends only on what
     * the model actually draws -- campaign_type, concept and brand colours --
     * so re-branding the logo or changing the badge never re-pays for a new
     * generation. The push title/message are intentionally not part of this.
     */
    public function artKey(BrandContext $brand): string
    {
        return substr(md5(implode('|', [
            'campaign-artwork-v9',
            Str::of((string) $this->campaignType)->lower()->trim(),
            Str::of((string) $this->imageConcept)->lower()->squish()->limit(160, ''),
            $brand->primaryColor,
            $brand->secondaryColor,
        ])), 0, 20);
    }

    /**
     * Key for the FINAL composited image = raw artwork + which logo + which
     * badge. Changing the logo or badge only re-composites (free), it does not
     * hit the image API again.
     */
    public function cacheKey(BrandContext $brand): string
    {
        return substr(md5(implode('|', [
            $this->artKey($brand),
            $brand->version(),
            Str::of((string) $this->promoBadgeText)->upper()->squish(),
        ])), 0, 20);
    }

    private static function clean(?string $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $value === '' ? null : Str::limit($value, $max, '');
    }
}
