<?php

namespace App\Services\Ai;

/**
 * Turns a structured campaign (type + goal + LLM image_concept + brand
 * colours) into a single image-generation prompt for gpt-image-1.
 *
 * Hard rules:
 *  - the push title/message are NEVER passed in here;
 *  - the prompt asks for a photographic campaign key visual, not a
 *    notification card / phone mockup / social post / poster;
 *  - important promotional text is NOT requested from the model (a badge is
 *    composited in PHP afterwards);
 *  - art direction changes per campaign_type so every campaign does not get
 *    the same generic food photo.
 */
class AiCampaignImagePromptBuilder
{
    /** campaign_type => art-direction sentence. */
    private const ART_DIRECTION = [
        'free_delivery' => 'A fresh ready-to-eat meal with a subtle, tasteful hint of delivery in the scene (a neatly packed order, a courier bag just out of focus, or gentle motion behind the food) -- the food stays the hero.',
        'discount' => 'A premium hero dish shot with bright, high-energy promotional lighting, bold appetising colour and a sense of abundance.',
        'cashback' => 'A generous, satisfying meal with a warm sense of value and reward -- rich portions, soft golden bokeh -- never depict literal money or coins.',
        'festival' => 'A warm Indian festive setting around a shareable feast: soft diya candlelight, marigold and fairy-light accents, celebratory table styling.',
        'restaurant_promo' => 'Restaurant-quality plating and fine-dining presentation, elegant tableware, careful garnish, shallow depth of field.',
        'food_recommendation' => 'Make the recommended dish or cuisine the single dominant subject, filling most of the frame, styled to look irresistible.',
        're_engagement' => 'Inviting, comforting home-style food, warm and nostalgic, steam rising, a "come back and eat" mood.',
        'new_restaurant' => 'A premium signature hero dish in sharp focus with a tastefully blurred upscale restaurant ambience behind it.',
        'breakfast' => 'Soft natural morning light on a fresh breakfast spread (eggs, parathas, fruit, coffee), bright and clean.',
        'lunch' => 'Bright natural daytime light on a hearty midday meal -- a full thali or a generous rice bowl.',
        'dinner' => 'Warm, low-key evening restaurant lighting, candle glow, indulgent dinner plating.',
        'late_night' => 'Dark, atmospheric, moody food photography with a single dramatic light on late-night comfort food.',
        'product_promotion' => 'A crisp premium product-style hero shot of a single signature item with clean commercial studio lighting.',
        'special_campaign' => 'A bold, premium celebratory food key visual with confident commercial styling.',
    ];

    private const GENERIC = 'A premium, appetising hero food shot with clean professional commercial advertising lighting.';

    public function build(CampaignImageSpec $spec, BrandContext $brand): string
    {
        $art = self::ART_DIRECTION[$spec->campaignType] ?? self::GENERIC;
        $concept = $spec->imageConcept !== null && trim($spec->imageConcept) !== ''
            ? trim($spec->imageConcept)
            : $art;

        $parts = [
            'Create premium commercial food-delivery campaign artwork: one realistic photographic key visual.',
            'Campaign concept: ' . $concept . '.',
            'Art direction: ' . $art,
        ];

        if ($spec->campaignGoal) {
            $parts[] = 'Intended mood/goal: ' . str_replace('_', ' ', $spec->campaignGoal) . '.';
        }

        $parts[] = 'Style: mouth-watering, realistic, premium food photography, a strong single central subject, '
            . 'professional commercial advertising lighting, rich colour, shallow depth of field.';
        $parts[] = sprintf(
            'Use a visual colour palette harmonised with the primary brand colour %s and the secondary colour %s '
            . '(express it through lighting, surfaces, props and background -- do NOT paint flat colour blocks, panels or gradients).',
            $brand->primaryColor,
            $brand->secondaryColor
        );
        $parts[] = 'Composition: wide 2:1 horizontal, designed specifically as rich push-notification artwork, '
            . 'mobile-friendly, high contrast, subject centred and clear of the extreme edges.';
        $parts[] = 'Leave the TOP-LEFT corner visually calm and uncluttered so the real app logo can be composited there later.';
        $parts[] = 'STRICTLY DO NOT INCLUDE: any smartphone or device, any device mockup, any app UI, any notification card, '
            . 'any notification or screen screenshot, any Instagram / Reels / story / social-media layout, any poster frame or border, '
            . 'any large headline, any body copy or paragraph text, any readable text on packaging / bags / boxes / signage / menus, '
            . 'any invented brand name or logo, any watermark.';

        if ($spec->promoBadgeText) {
            $parts[] = 'Do not render any promotional wording yourself -- a small badge is added separately afterwards.';
        }

        return implode(' ', $parts);
    }
}
