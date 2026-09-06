<?php

namespace App\Services\Restaurant;

use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantAdCampaign;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Executes the concrete app actions the restaurant AI assistant proposes —
 * coupons, ads, menu changes, storefront state. Every action:
 *   - is one of a fixed whitelist (never free-form SQL / model calls),
 *   - is scoped to the caller's own restaurant,
 *   - is proposed first (persisted to restaurant_ai_actions as "proposed") and
 *     only runs after the owner confirms via POST /assistant/action,
 *   - is capped / validated so the model can't set absurd values.
 *
 * The assistant only ever acts on the caller's own restaurant; it has no
 * competitor data and cannot touch other restaurants.
 */
class AssistantActionRunner
{
    /** tool => human contract shown to the model in the ANSWER prompt. */
    public const CATALOG = [
        'create_coupon' =>
            'Create a discount coupon for THIS restaurant. args: {title:string, discount_type:"percentage"|"flat", discount_value:number, min_order_amount?:number, max_discount_amount?:number, usage_limit?:int, days_valid?:int (default 14), code?:string (auto-generated if omitted)}',
        'set_coupon_status' =>
            'Enable or disable an existing coupon. args: {code:string, active:boolean}',
        'update_menu_item' =>
            'Change a dish\'s price / discounted price / availability. args: {item:string (name or id), price?:number, discounted_price?:number|null, is_available?:boolean}',
        'set_item_availability' =>
            'Mark a dish in or out of stock. args: {item:string, available:boolean, hours_until_back?:int}',
        'create_ad_campaign' =>
            'Create an ad campaign, saved as a draft in the Ads section for the owner to finish and publish. args: {name:string, max_cpc:number, daily_budget?:number, total_budget?:number, days?:int (default 30)}',
        'set_ad_status' =>
            'Pause or resume an ad campaign. args: {campaign:string (name or id), action:"pause"|"resume"}',
        'set_ad_budget' =>
            'Change an ad campaign\'s budget. args: {campaign:string, daily_budget?:number, total_budget?:number}',
        'set_restaurant_open' =>
            'Open or close the storefront for orders now. args: {open:boolean}',
        'update_hours' =>
            'Set daily opening hours. args: {open_time:"HH:MM", close_time:"HH:MM"}',
    ];

    /** tool => restaurant permission that authorises it (owner always allowed). */
    private const PERMS = [
        'create_coupon' => 'manage_promos',
        'set_coupon_status' => 'manage_promos',
        'update_menu_item' => 'manage_menu',
        'set_item_availability' => 'manage_menu',
        'create_ad_campaign' => 'manage_promos',
        'set_ad_status' => 'manage_promos',
        'set_ad_budget' => 'manage_promos',
        'set_restaurant_open' => 'view_dashboard',
        'update_hours' => 'manage_settings',
    ];

    public function catalogText(): string
    {
        $lines = [];
        foreach (self::CATALOG as $name => $desc) {
            $lines[] = "- {$name}: {$desc}";
        }

        return implode("\n", $lines);
    }

    public function isKnown(string $tool): bool
    {
        return array_key_exists($tool, self::CATALOG);
    }

    /**
     * Persist model-proposed actions as "proposed" rows and return them with ids.
     *
     * @param  array<int,array{tool?:string,args?:array,summary?:string}>  $actions
     * @return array<int,array{id:int,tool:string,args:array,summary:string,risk:string,status:string}>
     */
    public function propose(Restaurant $restaurant, string $conversationId, ?int $userId, array $actions): array
    {
        if (! Schema::hasTable('restaurant_ai_actions')) {
            return [];
        }

        $out = [];
        foreach (array_slice($actions, 0, 5) as $a) {
            $tool = is_array($a) ? ($a['tool'] ?? null) : null;
            if (! is_string($tool) || ! $this->isKnown($tool)) {
                continue;
            }
            $args = (is_array($a) && isset($a['args']) && is_array($a['args'])) ? $a['args'] : [];
            $summary = is_array($a) ? trim((string) ($a['summary'] ?? '')) : '';
            if ($summary === '') {
                $summary = $this->describe($tool, $args);
            }
            $risk = in_array($tool, ['set_restaurant_open', 'update_hours', 'create_ad_campaign', 'set_ad_budget'], true) ? 'medium' : 'low';

            $id = DB::table('restaurant_ai_actions')->insertGetId([
                'conversation_id' => $conversationId,
                'restaurant_id' => $restaurant->id,
                'user_id' => $userId,
                'tool' => $tool,
                'args' => json_encode($args),
                'summary' => $summary,
                'risk' => $risk,
                'status' => 'proposed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $out[] = [
                'id' => $id,
                'tool' => $tool,
                'args' => $args,
                'summary' => $summary,
                'risk' => $risk,
                'status' => 'proposed',
            ];
        }

        return $out;
    }

    /**
     * Run a previously-proposed action after the owner confirmed it.
     *
     * @return array{ok:bool,message:string,data?:array}
     */
    public function execute(object $action, Restaurant $restaurant, User $actor): array
    {
        $tool = (string) $action->tool;
        $args = is_string($action->args) ? (json_decode($action->args, true) ?: []) : (array) $action->args;

        if (! $this->isKnown($tool)) {
            return $this->fail($action, 'Unknown action.');
        }

        // Owner bypasses the permission matrix; staff need the mapped permission.
        $perm = self::PERMS[$tool] ?? null;
        if (! $actor->hasRole('restaurant_owner')
            && $perm
            && method_exists($actor, 'hasRestaurantPermission')
            && ! $actor->hasRestaurantPermission($perm)) {
            return $this->fail($action, "You don't have permission for this action.");
        }

        try {
            $result = match ($tool) {
                'create_coupon' => $this->createCoupon($restaurant, $args),
                'set_coupon_status' => $this->setCouponStatus($restaurant, $args),
                'update_menu_item' => $this->updateMenuItem($restaurant, $args),
                'set_item_availability' => $this->setItemAvailability($restaurant, $args),
                'create_ad_campaign' => $this->createAdCampaign($restaurant, $args),
                'set_ad_status' => $this->setAdStatus($restaurant, $args),
                'set_ad_budget' => $this->setAdBudget($restaurant, $args),
                'set_restaurant_open' => $this->setRestaurantOpen($restaurant, $args),
                'update_hours' => $this->updateHours($restaurant, $args),
                default => ['ok' => false, 'message' => 'Unknown action.'],
            };
        } catch (\Throwable $e) {
            report($e);
            return $this->fail($action, 'That action could not be completed: ' . $e->getMessage());
        }

        $this->mark($action, $result['ok'] ? 'executed' : 'failed', $result);

        return $result;
    }

    public function cancel(object $action): void
    {
        $this->mark($action, 'cancelled', ['ok' => false, 'message' => 'Dismissed by owner.']);
    }

    // ------------------------------------------------------------- handlers

    private function createCoupon(Restaurant $restaurant, array $a): array
    {
        $type = in_array(($a['discount_type'] ?? 'flat'), ['percentage', 'percent', '%'], true) ? 'percentage' : 'flat';
        $value = round((float) ($a['discount_value'] ?? 0), 2);
        if ($value <= 0) {
            return ['ok' => false, 'message' => 'Give a discount value greater than 0.'];
        }
        if ($type === 'percentage') {
            $value = min($value, 90.0);
        } else {
            $value = min($value, 5000.0);
        }

        $code = strtoupper(trim((string) ($a['code'] ?? '')));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?: '';
        if ($code === '') {
            do {
                $code = 'SR' . strtoupper(Str::random(6));
            } while (PromoCode::where('code', $code)->exists());
        } elseif (PromoCode::where('code', $code)->exists()) {
            return ['ok' => false, 'message' => "Coupon code {$code} already exists."];
        }

        $daysValid = (int) ($a['days_valid'] ?? 14);
        $daysValid = max(1, min($daysValid, 180));

        $coupon = PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => $code,
            'title' => trim((string) ($a['title'] ?? 'Special offer')) ?: 'Special offer',
            'description' => trim((string) ($a['description'] ?? '')) ?: null,
            'created_by_type' => 'restaurant',
            'discount_type' => $type,
            'discount_value' => $value,
            'min_order_amount' => isset($a['min_order_amount']) ? max(0, round((float) $a['min_order_amount'], 2)) : null,
            'max_discount_amount' => isset($a['max_discount_amount']) ? max(0, round((float) $a['max_discount_amount'], 2)) : null,
            'usage_limit' => isset($a['usage_limit']) ? max(1, (int) $a['usage_limit']) : null,
            'audience_type' => 'all',
            'coupon_type' => 'public',
            'promotion_type' => 'coupon',
            'start_date' => now(),
            'end_date' => now()->addDays($daysValid),
            'is_active' => true,
        ]);

        $label = $type === 'percentage' ? "{$value}% off" : "₹{$value} off";

        return [
            'ok' => true,
            'message' => "Coupon {$coupon->code} created — {$label}, valid {$daysValid} days. It's live at checkout now.",
            'data' => ['code' => $coupon->code, 'id' => $coupon->id],
        ];
    }

    private function setCouponStatus(Restaurant $restaurant, array $a): array
    {
        $code = strtoupper(trim((string) ($a['code'] ?? '')));
        $coupon = PromoCode::where('restaurant_id', $restaurant->id)
            ->where(fn ($q) => $q->where('code', $code)->orWhere('id', (int) ($a['code'] ?? 0)))
            ->first();
        if (! $coupon) {
            return ['ok' => false, 'message' => "No coupon {$code} on this restaurant."];
        }

        $coupon->is_active = (bool) ($a['active'] ?? true);
        $coupon->save();

        return [
            'ok' => true,
            'message' => "Coupon {$coupon->code} " . ($coupon->is_active ? 'enabled' : 'disabled') . '.',
            'data' => ['code' => $coupon->code, 'is_active' => $coupon->is_active],
        ];
    }

    private function resolveMenuItem(Restaurant $restaurant, array $a): ?object
    {
        $item = trim((string) ($a['item'] ?? ''));
        if ($item === '') {
            return null;
        }
        $q = DB::table('menu_items')->where('restaurant_id', $restaurant->id);
        if (ctype_digit($item)) {
            return $q->where('id', (int) $item)->first();
        }

        return $q->where('name', $item)->first() ?: $q->where('name', 'like', '%' . $item . '%')->first();
    }

    private function updateMenuItem(Restaurant $restaurant, array $a): array
    {
        $row = $this->resolveMenuItem($restaurant, $a);
        if (! $row) {
            return ['ok' => false, 'message' => "No dish matching \"" . ($a['item'] ?? '') . "\"."];
        }

        $update = [];
        if (array_key_exists('price', $a) && $a['price'] !== null) {
            $price = round((float) $a['price'], 2);
            if ($price < 1 || $price > 100000) {
                return ['ok' => false, 'message' => 'Price must be between ₹1 and ₹100000.'];
            }
            $update['price'] = $price;
        }
        if (array_key_exists('discounted_price', $a)) {
            if ($a['discounted_price'] === null || $a['discounted_price'] === '') {
                $update['discounted_price'] = null;
            } else {
                $dp = round((float) $a['discounted_price'], 2);
                $basis = $update['price'] ?? (float) $row->price;
                if ($dp <= 0 || $dp >= $basis) {
                    return ['ok' => false, 'message' => 'Discounted price must be above 0 and below the normal price.'];
                }
                $update['discounted_price'] = $dp;
            }
        }
        if (array_key_exists('is_available', $a)) {
            $update['is_available'] = (bool) $a['is_available'] ? 1 : 0;
        }
        if (empty($update)) {
            return ['ok' => false, 'message' => 'Nothing to change — give a price, discounted_price or is_available.'];
        }

        $update['updated_at'] = now();
        DB::table('menu_items')->where('id', $row->id)->update($update);

        return [
            'ok' => true,
            'message' => "\"{$row->name}\" updated: " . collect($update)->except('updated_at')
                ->map(fn ($v, $k) => "{$k}=" . (is_null($v) ? 'cleared' : $v))->implode(', '),
            'data' => ['id' => $row->id, 'name' => $row->name],
        ];
    }

    private function setItemAvailability(Restaurant $restaurant, array $a): array
    {
        $row = $this->resolveMenuItem($restaurant, $a);
        if (! $row) {
            return ['ok' => false, 'message' => "No dish matching \"" . ($a['item'] ?? '') . "\"."];
        }
        $available = (bool) ($a['available'] ?? true);
        $update = ['is_available' => $available ? 1 : 0, 'updated_at' => now()];

        if (! $available && ! empty($a['hours_until_back'])) {
            $update['unavailable_until'] = now()->addHours(max(1, min((int) $a['hours_until_back'], 168)));
        }
        if ($available) {
            $update['unavailable_until'] = null;
        }

        DB::table('menu_items')->where('id', $row->id)->update($update);

        return [
            'ok' => true,
            'message' => "\"{$row->name}\" marked " . ($available ? 'in stock' : 'out of stock')
                . (isset($update['unavailable_until']) && $update['unavailable_until'] ? ' until ' . Carbon::parse($update['unavailable_until'])->format('d M H:i') : '') . '.',
            'data' => ['id' => $row->id, 'is_available' => $available],
        ];
    }

    private function resolveCampaign(Restaurant $restaurant, array $a): ?RestaurantAdCampaign
    {
        $c = trim((string) ($a['campaign'] ?? ''));
        $q = RestaurantAdCampaign::where('restaurant_id', $restaurant->id);
        if (ctype_digit($c)) {
            return $q->find((int) $c);
        }

        return $q->where('name', $c)->first() ?: $q->where('name', 'like', '%' . $c . '%')->first();
    }

    private function createAdCampaign(Restaurant $restaurant, array $a): array
    {
        $name = trim((string) ($a['name'] ?? '')) ?: ($restaurant->name . ' promo');
        $maxCpc = round((float) ($a['max_cpc'] ?? 2), 2);
        if ($maxCpc < 0.5 || $maxCpc > 1000) {
            return ['ok' => false, 'message' => 'Max cost-per-click must be between ₹0.5 and ₹1000.'];
        }
        $days = max(1, min((int) ($a['days'] ?? 30), 365));

        $campaign = RestaurantAdCampaign::create([
            'restaurant_id' => $restaurant->id,
            'name' => $name,
            'status' => RestaurantAdCampaign::STATUS_DRAFT,
            'max_cpc' => $maxCpc,
            'daily_budget' => isset($a['daily_budget']) ? max(1, round((float) $a['daily_budget'], 2)) : null,
            'total_budget' => isset($a['total_budget']) ? max(1, round((float) $a['total_budget'], 2)) : null,
            'starts_at' => now(),
            'ends_at' => now()->addDays($days),
        ]);

        return [
            'ok' => true,
            'message' => "Done — I've set up \"{$name}\" as a draft in your Ads section. Open it there to pick who sees it and publish when you're ready.",
            'data' => ['id' => $campaign->id, 'name' => $name],
        ];
    }

    private function setAdStatus(Restaurant $restaurant, array $a): array
    {
        $campaign = $this->resolveCampaign($restaurant, $a);
        if (! $campaign) {
            return ['ok' => false, 'message' => 'No matching ad campaign.'];
        }
        $act = ($a['action'] ?? '') === 'resume' ? 'resume' : 'pause';

        if ($act === 'pause') {
            $campaign->status = RestaurantAdCampaign::STATUS_PAUSED;
            $campaign->paused_reason = 'Paused via AI assistant';
        } else {
            if (! in_array($campaign->status, [RestaurantAdCampaign::STATUS_PAUSED, RestaurantAdCampaign::STATUS_ACTIVE], true)) {
                return ['ok' => false, 'message' => "Campaign is \"{$campaign->status}\" — only a paused campaign can be resumed."];
            }
            $campaign->status = RestaurantAdCampaign::STATUS_ACTIVE;
            $campaign->paused_reason = null;
        }
        $campaign->save();

        return ['ok' => true, 'message' => "Campaign \"{$campaign->name}\" " . ($act === 'pause' ? 'paused' : 'resumed') . '.', 'data' => ['id' => $campaign->id, 'status' => $campaign->status]];
    }

    private function setAdBudget(Restaurant $restaurant, array $a): array
    {
        $campaign = $this->resolveCampaign($restaurant, $a);
        if (! $campaign) {
            return ['ok' => false, 'message' => 'No matching ad campaign.'];
        }
        $changed = [];
        if (isset($a['daily_budget'])) {
            $campaign->daily_budget = max(1, round((float) $a['daily_budget'], 2));
            $changed[] = "daily ₹{$campaign->daily_budget}";
        }
        if (isset($a['total_budget'])) {
            $campaign->total_budget = max(1, round((float) $a['total_budget'], 2));
            $changed[] = "total ₹{$campaign->total_budget}";
        }
        if (empty($changed)) {
            return ['ok' => false, 'message' => 'Give a daily_budget or total_budget.'];
        }
        $campaign->save();

        return ['ok' => true, 'message' => "Budget updated for \"{$campaign->name}\": " . implode(', ', $changed) . '.', 'data' => ['id' => $campaign->id]];
    }

    private function setRestaurantOpen(Restaurant $restaurant, array $a): array
    {
        $open = (bool) ($a['open'] ?? true);
        $restaurant->is_open = $open;
        if (! $open) {
            $restaurant->offline_reason = 'Closed via AI assistant';
        }
        $restaurant->save();

        return ['ok' => true, 'message' => 'Storefront is now ' . ($open ? 'OPEN' : 'CLOSED') . ' for orders.', 'data' => ['is_open' => $open]];
    }

    private function updateHours(Restaurant $restaurant, array $a): array
    {
        $open = $this->normTime($a['open_time'] ?? null);
        $close = $this->normTime($a['close_time'] ?? null);
        if (! $open || ! $close) {
            return ['ok' => false, 'message' => 'Give open_time and close_time as HH:MM.'];
        }
        $restaurant->open_time = $open;
        $restaurant->close_time = $close;
        $restaurant->save();

        return ['ok' => true, 'message' => "Hours set to {$open}–{$close}.", 'data' => ['open_time' => $open, 'close_time' => $close]];
    }

    // ------------------------------------------------------------- helpers

    private function normTime($raw): ?string
    {
        if (! is_string($raw) || ! preg_match('/^(\d{1,2}):(\d{2})$/', trim($raw), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $min);
    }

    private function describe(string $tool, array $args): string
    {
        return $tool . '(' . collect($args)->map(fn ($v, $k) => "{$k}: " . (is_scalar($v) ? $v : json_encode($v)))->implode(', ') . ')';
    }

    private function mark(object $action, string $status, array $result): void
    {
        if (! Schema::hasTable('restaurant_ai_actions')) {
            return;
        }
        DB::table('restaurant_ai_actions')->where('id', $action->id)->update([
            'status' => $status,
            'result' => json_encode($result),
            'error' => $result['ok'] ? null : ($result['message'] ?? null),
            'executed_at' => in_array($status, ['executed', 'failed'], true) ? now() : null,
            'updated_at' => now(),
        ]);
    }

    private function fail(object $action, string $message): array
    {
        $out = ['ok' => false, 'message' => $message];
        $this->mark($action, 'failed', $out);

        return $out;
    }
}
