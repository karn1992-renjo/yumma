<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Restaurant\Concerns\ResolvesRestaurantScope;
use App\Models\Restaurant;
use App\Services\Ai\AiCostAwareRouter;
use App\Services\Ai\AiSettingsService;
use App\Services\Restaurant\AssistantActionRunner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Restaurant-facing AI growth assistant — real, data-grounded, OpenAI-driven.
 *
 *   GET  /api/restaurant/assistant/status
 *   POST /api/restaurant/assistant/message   body { message, conversation_id? }
 *   GET  /api/restaurant/assistant/history?conversation_id=
 *
 * How a reply is produced (two OpenAI passes, no hard-coded answers):
 *   1. PLAN  — the model reads the owner's message + recent turns and picks which
 *              read-only data tools to run (from {@see self::TOOLS}) with args.
 *   2. FETCH — we run those queries against THIS restaurant's live data.
 *   3. ANSWER— the model answers from the fetched data as JSON
 *              { reply, plan:[{title,steps}], suggestions:[] }.
 *
 * Provider + key + kill switch all come from the admin AI Control Center
 * (AiSettingsService / AiCostAwareRouter). Every tool is restaurant-scoped and
 * row-capped; the model never touches the DB directly.
 */
class RestaurantAssistantController extends Controller
{
    use ResolvesRestaurantScope;

    /** Read-only data tools the planner may request, with a one-line contract. */
    private const TOOLS = [
        'sales_overview'   => 'Totals for a period: orders, revenue, avg order value, restaurant earning, cancellations, and the same for the previous period. args: {days:int=30}',
        'sales_by_day'     => 'Daily series of orders + revenue. Use for trend / "why are orders down". args: {days:int=30}',
        'sales_by_hour'    => 'Orders grouped by hour of day (0-23) and by weekday. Use for peak hours / staffing. args: {days:int=30}',
        'item_performance' => 'Per-dish units sold, revenue, availability and rating, best first. args: {days:int=30, limit:int=20}',
        'item_lookup'      => 'One dish in detail incl. 30/60/90-day units trend. args: {name:string}',
        'menu_health'      => 'Menu size, available vs unavailable, price range, and dishes with zero sales in the period. args: {days:int=30}',
        'payouts_recent'   => 'Recent payout cycles with full money breakdown (gross, commission, commission GST, gateway fee, TDS, TCS, net, status). args: {limit:int=6}',
        'payout_explain'   => 'One payout cycle broken down line by line, with order count. args: {id:int=null means latest}',
        'reviews_summary'  => 'Average rating, rating spread, and the most recent low (<=3) reviews with text. args: {days:int=60}',
        'promotions_active'=> 'This restaurant\'s current promotions and coupon codes with usage counts. args: {}',
        'cancellations'    => 'Cancelled-order count, cancel rate, and the top cancellation reasons. args: {days:int=30}',
    ];

    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiCostAwareRouter $router,
        private readonly AssistantActionRunner $actions,
    ) {
    }

    // ------------------------------------------------------------------ status

    public function status(): \Illuminate\Http\JsonResponse
    {
        $health = $this->settings->health();
        $enabled = (bool) ($health['enabled'] ?? $this->settings->bool('ai_enabled'))
            && (($health['openai_configured'] ?? false) || ($health['gemini_configured'] ?? false));

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $enabled,
                'service_enabled' => (bool) ($health['enabled'] ?? false),
            ],
        ]);
    }

    // ----------------------------------------------------------------- history

    public function history(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $restaurant = $this->resolveSingleRestaurant($request, $user);

        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'No restaurant found.'], 404);
        }
        if (! Schema::hasTable('restaurant_ai_messages')) {
            return response()->json(['success' => true, 'data' => ['conversation_id' => null, 'messages' => []]]);
        }

        $conversationId = $request->input('conversation_id')
            ?: DB::table('restaurant_ai_messages')
                ->where('restaurant_id', $restaurant->id)
                ->orderByDesc('id')
                ->value('conversation_id');

        if (! $conversationId) {
            return response()->json(['success' => true, 'data' => ['conversation_id' => null, 'messages' => []]]);
        }

        $messages = DB::table('restaurant_ai_messages')
            ->where('restaurant_id', $restaurant->id)
            ->where('conversation_id', $conversationId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'role' => $row->role,
                'text' => $row->content,
                'plan' => $row->plan ? json_decode($row->plan, true) : [],
            ]);

        return response()->json([
            'success' => true,
            'data' => ['conversation_id' => $conversationId, 'messages' => $messages],
        ]);
    }

    /**
     * Past conversations for this restaurant, newest first.
     *
     *   GET /api/restaurant/assistant/conversations
     *   -> { data: { conversations: [{ conversation_id, title, preview, message_count, updated_at }] } }
     */
    public function conversations(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $restaurant = $this->resolveSingleRestaurant($request, $user);

        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'No restaurant found.'], 404);
        }
        if (! Schema::hasTable('restaurant_ai_messages')) {
            return response()->json(['success' => true, 'data' => ['conversations' => []]]);
        }

        $groups = DB::table('restaurant_ai_messages')
            ->where('restaurant_id', $restaurant->id)
            ->selectRaw('conversation_id, COUNT(*) as message_count, MAX(id) as last_id, MAX(created_at) as updated_at')
            ->groupBy('conversation_id')
            ->orderByDesc('last_id')
            ->limit(40)
            ->get();

        // First user line of each conversation = its title.
        $firstUserByConv = DB::table('restaurant_ai_messages')
            ->where('restaurant_id', $restaurant->id)
            ->where('role', 'user')
            ->whereIn('conversation_id', $groups->pluck('conversation_id'))
            ->orderBy('id')
            ->get(['conversation_id', 'content'])
            ->groupBy('conversation_id');

        $lastById = DB::table('restaurant_ai_messages')
            ->whereIn('id', $groups->pluck('last_id'))
            ->get(['id', 'content'])
            ->keyBy('id');

        $conversations = $groups->map(function ($g) use ($firstUserByConv, $lastById) {
            $title = optional($firstUserByConv->get($g->conversation_id))->first()->content ?? 'Conversation';
            $preview = optional($lastById->get($g->last_id))->content ?? '';

            return [
                'conversation_id' => $g->conversation_id,
                'title' => Str::limit(trim((string) $title), 60),
                'preview' => Str::limit(trim((string) $preview), 90),
                'message_count' => (int) $g->message_count,
                'updated_at' => $g->updated_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => ['conversations' => $conversations],
        ]);
    }

    // ----------------------------------------------------------------- message

    public function message(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $restaurant = $this->resolveSingleRestaurant($request, $user);

        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'No restaurant found.'], 404);
        }
        if (! $this->settings->bool('ai_enabled')) {
            return response()->json([
                'success' => false,
                'message' => "I'm offline for a bit and can't dig into your numbers right now. Please try again in a little while.",
            ], 503);
        }

        $conversationId = ($data['conversation_id'] ?? null) ?: (string) Str::uuid();
        $recent = $this->recentTurns($restaurant->id, $conversationId);

        // 1. PLAN — let the model decide what data it needs.
        $plan = $this->planFetches($restaurant, $data['message'], $recent);

        // 2. FETCH — run the requested read-only tools against live data.
        $facts = [];
        foreach ($plan['fetch'] as $call) {
            $tool = $call['tool'] ?? null;
            if (! is_string($tool) || ! array_key_exists($tool, self::TOOLS)) {
                continue;
            }
            $facts[$tool] = $this->runTool($restaurant, $tool, is_array($call['args'] ?? null) ? $call['args'] : []);
        }

        // 3. ANSWER — model writes the reply grounded in $facts.
        $answer = $this->composeAnswer($restaurant, $data['message'], $recent, $plan['intent'] ?? '', $facts);
        if ($answer === null) {
            return response()->json([
                'success' => false,
                'message' => "Sorry, I hit a snag pulling that together. Give me another try in a moment?",
            ], 502);
        }

        // 4. PROPOSE — persist any actions the model wants to take; the owner
        //    confirms each one via POST /assistant/action before it runs.
        $proposed = ! empty($answer['actions'])
            ? $this->actions->propose($restaurant, $conversationId, $user?->id, $answer['actions'])
            : [];

        $this->persist($restaurant, $user?->id, $conversationId, 'user', $data['message'], [], []);
        $this->persist($restaurant, $user?->id, $conversationId, 'assistant', $answer['reply'], $answer['plan'], $answer['suggestions']);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversationId,
                'reply' => $answer['reply'],
                'plan' => $answer['plan'],
                'suggestions' => $answer['suggestions'],
                'actions' => $proposed,
                'used_data' => array_keys($facts),
            ],
        ]);
    }

    /**
     * Confirm (or dismiss) an action the assistant proposed, then run it.
     *
     *   POST /api/restaurant/assistant/action  body { action_id, confirm:bool }
     *   -> { data: { action_id, status, ok, message, result? } }
     */
    public function action(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'action_id' => ['required', 'integer'],
            'confirm' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $restaurant = $this->resolveSingleRestaurant($request, $user);
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'No restaurant found.'], 404);
        }
        if (! Schema::hasTable('restaurant_ai_actions')) {
            return response()->json(['success' => false, 'message' => 'Assistant actions are not available yet.'], 503);
        }

        $action = DB::table('restaurant_ai_actions')
            ->where('id', $data['action_id'])
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (! $action) {
            return response()->json(['success' => false, 'message' => 'Action not found.'], 404);
        }
        if ($action->status !== 'proposed') {
            return response()->json([
                'success' => true,
                'data' => ['action_id' => $action->id, 'status' => $action->status, 'ok' => $action->status === 'executed',
                    'message' => 'This action was already ' . $action->status . '.'],
            ]);
        }

        if (! $data['confirm']) {
            $this->actions->cancel($action);
            return response()->json([
                'success' => true,
                'data' => ['action_id' => $action->id, 'status' => 'cancelled', 'ok' => false, 'message' => 'Dismissed.'],
            ]);
        }

        $result = $this->actions->execute($action, $restaurant, $user);

        $this->persist(
            $restaurant,
            $user?->id,
            (string) $action->conversation_id,
            'assistant',
            ($result['ok'] ? '✓ ' : '⚠ ') . $result['message'],
            [],
            []
        );

        return response()->json([
            'success' => true,
            'data' => [
                'action_id' => $action->id,
                'status' => $result['ok'] ? 'executed' : 'failed',
                'ok' => $result['ok'],
                'message' => $result['message'],
                'result' => $result['data'] ?? null,
            ],
        ]);
    }

    // ------------------------------------------------------------- AI: planning

    private function planFetches(Restaurant $restaurant, string $message, array $recent): array
    {
        $catalog = collect(self::TOOLS)->map(fn ($desc, $name) => "- {$name}: {$desc}")->implode("\n");
        $history = $this->historyBlock($recent);

        $prompt = <<<PROMPT
You are the planning step of a restaurant analytics assistant for "{$restaurant->name}".
Read the owner's message and decide which read-only DATA TOOLS to run so the next
step can answer accurately from real numbers. Request only what's relevant (1-4
tools is normal; pick more only if truly needed). Never invent tools.

DATA TOOLS:
{$catalog}

Return ONLY JSON:
{"intent": "<one short sentence: what the owner wants>",
 "fetch": [ {"tool": "<name>", "args": { ... }}, ... ]}

CONVERSATION SO FAR:
{$history}
OWNER: {$message}
PROMPT;

        $res = $this->ask($prompt, 'assistant_plan', 0.1);
        $decoded = is_string($res) ? json_decode($res, true) : null;

        $fetch = [];
        if (is_array($decoded) && isset($decoded['fetch']) && is_array($decoded['fetch'])) {
            $fetch = $decoded['fetch'];
        }
        // Fallback: if planning failed or asked for nothing, grab a sensible base set.
        if (empty($fetch)) {
            $fetch = [
                ['tool' => 'sales_overview', 'args' => ['days' => 30]],
                ['tool' => 'item_performance', 'args' => ['days' => 30, 'limit' => 15]],
                ['tool' => 'payouts_recent', 'args' => ['limit' => 3]],
            ];
        }

        return [
            'intent' => is_array($decoded) ? (string) ($decoded['intent'] ?? '') : '',
            'fetch' => array_slice($fetch, 0, 6),
        ];
    }

    // -------------------------------------------------------------- AI: answering

    private function composeAnswer(Restaurant $restaurant, string $message, array $recent, string $intent, array $facts): ?array
    {
        $factsJson = json_encode(
            [
                'restaurant' => [
                    'name' => $restaurant->name,
                    'city' => $restaurant->city,
                    'is_open' => (bool) $restaurant->is_open,
                    'today' => now()->toDateString(),
                    'currency' => 'INR',
                ],
                'data' => $facts,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $history = $this->historyBlock($recent);
        $actionCatalog = $this->actions->catalogText();
        $app = $this->appName();

        $prompt = <<<PROMPT
You are the personal growth partner for the owner of "{$restaurant->name}" on
{$app} — you both advise AND get things done in their {$app} restaurant account.
Answer using ONLY the DATA below — real figures, real dish names, the actual
period. If the data doesn't cover something, just say so.

VOICE: Talk like a real person who knows this restaurant well and is on the
owner's side. Warm, plain, first person ("I looked at your last 30 days…",
"you're losing orders on…"). Short sentences and a short paragraph or two — not a
bulleted report, no headers, no corporate filler, never "As an AI". Contractions
are good. Encouraging but honest. Use ₹. Keep "reply" under about 130 words.
Use "plan" ONLY if they ask for a plan / strategy / how-to. "suggestions" = 2-4
short, natural things they might ask next.

YOU ARE THE RESTAURANT'S ASSISTANT — not {$app}'s support desk. Never tell the
owner to "contact the admin", "reach out to the platform team", "raise a ticket",
"ask support", or mention an admin panel, settings, approvals or the AI Control
Center. You act for the owner. If something genuinely can't be changed from the
restaurant app, say so in one plain sentence and move on to what they CAN do.

SCOPE: You only ever work on THIS ONE restaurant. You have NO data about any
other restaurant, market, or competitor. If they ask which competitors to look at
or to compare with other restaurants, tell them plainly you can only see their
own store, then talk about their own numbers, trends and menu. Never invent
competitor names or figures. NEVER recommend, compare with, or suggest listing /
advertising / selling on any other food-delivery app or marketplace (Zomato,
Swiggy, Magicpin, ONDC, uber eats, etc.) — don't even name them. Every idea uses
their own {$app} levers: coupons, ads, menu, pricing, hours, dish photos, ratings
and reviews, prep time, availability. (Them sharing their own {$app} store link
on their own social media is fine to mention; a rival delivery app is not.)

ACTIONS — you can actually perform these tasks. When the owner asks you to DO
something (create/enable/disable a coupon, create/pause/resume/re-budget an ad,
change a dish price or availability, open/close the store, set hours) OR clearly
tells you to go ahead with a suggestion, put one or more entries in "actions".
Each: {"tool": <name>, "args": {…per the tool}, "summary": "<one line the owner
will see on the confirm button>"}. Do NOT claim the task is done — these are
proposals the owner taps to confirm; the app runs them and reports back. Propose
an action only when the request is concrete (you have the numbers you need); if
something's missing, ask for it in "reply" instead. Never propose an action about
another restaurant. Available tools:
{$actionCatalog}

LANGUAGE: Detect the language/script of the owner's latest message and write the
WHOLE response — "reply", plan text, suggestions, and every action "summary" — in
it:
  - English -> English.
  - Devanagari Hindi (e.g. "मेरे ऑर्डर क्यों कम हैं") -> Devanagari Hindi.
  - Hinglish (Hindi in Roman letters, often mixed with English, e.g. "mera veg
    thali ka coupon bana do 15% ka") -> natural Roman-script Hinglish, not pure
    Hindi and not pure English.
Match the script the owner used most. Keep dish names, coupon codes, numbers and
₹ amounts as-is. JSON keys and every "tool" value stay in English.

Owner intent (planner's read): {$intent}

DATA (live, this restaurant):
{$factsJson}

Return ONLY JSON:
{"reply": string,
 "plan": [{"title": string, "steps": [string, ...]}],
 "suggestions": [string, ...],
 "actions": [{"tool": string, "args": object, "summary": string}]}

CONVERSATION SO FAR:
{$history}
OWNER: {$message}
PROMPT;

        $res = $this->ask($prompt, 'assistant_answer', 0.4);
        if ($res === null) {
            return null;
        }

        $decoded = json_decode($res, true);
        if (! is_array($decoded)) {
            return ['reply' => trim($res) ?: "Sorry, I didn't quite catch that — can you say it another way?", 'plan' => [], 'suggestions' => [], 'actions' => []];
        }

        $plan = [];
        foreach ((array) ($decoded['plan'] ?? $decoded['plans'] ?? []) as $p) {
            if (! is_array($p)) {
                continue;
            }
            $plan[] = [
                'title' => (string) ($p['title'] ?? 'Plan'),
                'steps' => array_values(array_map('strval', (array) ($p['steps'] ?? []))),
            ];
        }

        $actions = [];
        foreach ((array) ($decoded['actions'] ?? []) as $act) {
            if (! is_array($act) || empty($act['tool']) || ! $this->actions->isKnown((string) $act['tool'])) {
                continue;
            }
            $actions[] = [
                'tool' => (string) $act['tool'],
                'args' => is_array($act['args'] ?? null) ? $act['args'] : [],
                'summary' => trim((string) ($act['summary'] ?? '')),
            ];
        }

        return [
            'reply' => (string) ($decoded['reply'] ?? $decoded['message'] ?? ''),
            'plan' => $plan,
            'suggestions' => array_values(array_map('strval', (array) ($decoded['suggestions'] ?? []))),
            'actions' => $actions,
        ];
    }

    private function ask(string $prompt, string $feature, float $temperature): ?string
    {
        $result = $this->router->ask($prompt, ['feature' => $feature], [
            'provider' => 'openai',
            'temperature' => $temperature,
        ]);

        return ($result['success'] ?? false) ? (string) ($result['content'] ?? '') : null;
    }

    // --------------------------------------------------------------- data tools

    /** @return array<string,mixed> */
    private function runTool(Restaurant $restaurant, string $tool, array $args): array
    {
        $rid = $restaurant->id;
        $days = (int) max(1, min((int) ($args['days'] ?? 30), 180));
        $since = now()->copy()->subDays($days);

        try {
            return match ($tool) {
                'sales_overview'    => $this->toolSalesOverview($rid, $days),
                'sales_by_day'      => $this->toolSalesByDay($rid, $since),
                'sales_by_hour'     => $this->toolSalesByHour($rid, $since),
                'item_performance'  => $this->toolItemPerformance($rid, $since, (int) max(1, min((int) ($args['limit'] ?? 20), 50))),
                'item_lookup'       => $this->toolItemLookup($rid, (string) ($args['name'] ?? '')),
                'menu_health'       => $this->toolMenuHealth($rid, $since),
                'payouts_recent'    => $this->toolPayoutsRecent($rid, (int) max(1, min((int) ($args['limit'] ?? 6), 24))),
                'payout_explain'    => $this->toolPayoutExplain($rid, isset($args['id']) ? (int) $args['id'] : null),
                'reviews_summary'   => $this->toolReviewsSummary($rid, (int) max(7, min((int) ($args['days'] ?? 60), 365))),
                'promotions_active' => $this->toolPromotionsActive($rid),
                'cancellations'     => $this->toolCancellations($rid, $since, $days),
                default             => ['error' => 'unknown tool'],
            };
        } catch (\Throwable $e) {
            report($e);
            return ['error' => 'data unavailable'];
        }
    }

    private function paidOrders(int $rid)
    {
        return DB::table('orders')
            ->where('orders.restaurant_id', $rid)
            ->where(function ($q) {
                $q->where('orders.payment_status', 'success')
                    ->orWhereIn('orders.payment_method', ['cod', 'cash', 'cash_on_delivery'])
                    ->orWhereIn('orders.delivery_payment_mode', ['cod', 'cash', 'cash_on_delivery']);
            })
            ->whereNotIn('orders.status', ['cancelled']);
    }

    private function toolSalesOverview(int $rid, int $days): array
    {
        $win = fn ($from, $to) => (clone $this->paidOrders($rid))
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('COUNT(*) c, COALESCE(SUM(total),0) revenue, COALESCE(AVG(total),0) aov, COALESCE(SUM(restaurant_earning),0) earning')
            ->first();

        $now = now();
        $cur = $win($now->copy()->subDays($days), $now);
        $prev = $win($now->copy()->subDays($days * 2), $now->copy()->subDays($days));

        $cancelled = (int) DB::table('orders')
            ->where('restaurant_id', $rid)
            ->where('status', 'cancelled')
            ->where('created_at', '>=', $now->copy()->subDays($days))
            ->count();

        return [
            'period' => "last {$days} days",
            'orders' => (int) $cur->c,
            'revenue' => round((float) $cur->revenue, 2),
            'avg_order_value' => round((float) $cur->aov, 2),
            'restaurant_earning' => round((float) $cur->earning, 2),
            'cancelled_orders' => $cancelled,
            'previous_period' => [
                'orders' => (int) $prev->c,
                'revenue' => round((float) $prev->revenue, 2),
                'avg_order_value' => round((float) $prev->aov, 2),
            ],
            'change' => [
                'orders_pct' => $this->pct((int) $prev->c, (int) $cur->c),
                'revenue_pct' => $this->pct((float) $prev->revenue, (float) $cur->revenue),
            ],
        ];
    }

    private function toolSalesByDay(int $rid, Carbon $since): array
    {
        $rows = (clone $this->paidOrders($rid))
            ->where('orders.created_at', '>=', $since)
            ->selectRaw('DATE(orders.created_at) d, COUNT(*) orders, COALESCE(SUM(total),0) revenue')
            ->groupBy('d')->orderBy('d')->get();

        return [
            'from' => $since->toDateString(),
            'series' => $rows->map(fn ($r) => [
                'date' => $r->d,
                'orders' => (int) $r->orders,
                'revenue' => round((float) $r->revenue, 2),
            ])->all(),
        ];
    }

    private function toolSalesByHour(int $rid, Carbon $since): array
    {
        $byHour = (clone $this->paidOrders($rid))
            ->where('orders.created_at', '>=', $since)
            ->selectRaw('HOUR(orders.created_at) h, COUNT(*) c')
            ->groupBy('h')->orderBy('h')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->h => (int) $r->c]);

        $byDow = (clone $this->paidOrders($rid))
            ->where('orders.created_at', '>=', $since)
            ->selectRaw('DAYNAME(orders.created_at) dow, COUNT(*) c')
            ->groupBy('dow')->get()
            ->mapWithKeys(fn ($r) => [(string) $r->dow => (int) $r->c]);

        return [
            'since' => $since->toDateString(),
            'orders_by_hour' => collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => $byHour[$h] ?? 0])->all(),
            'orders_by_weekday' => $byDow->all(),
        ];
    }

    private function toolItemPerformance(int $rid, Carbon $since, int $limit): array
    {
        if (! Schema::hasTable('order_items')) {
            return ['items' => []];
        }
        $rows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->where('orders.restaurant_id', $rid)
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', ['cancelled'])
            ->groupBy('order_items.menu_item_id', 'menu_items.name', 'menu_items.is_available', 'menu_items.rating', 'menu_items.price')
            ->selectRaw("COALESCE(menu_items.name,'Item') name, menu_items.is_available, menu_items.rating, menu_items.price")
            ->selectRaw('SUM(order_items.quantity) qty, SUM(order_items.total_price) revenue')
            ->orderByDesc('qty')->limit($limit)->get();

        return [
            'period_from' => $since->toDateString(),
            'items' => $rows->map(fn ($r) => [
                'name' => $r->name,
                'qty' => (int) $r->qty,
                'revenue' => round((float) $r->revenue, 2),
                'price' => $r->price !== null ? round((float) $r->price, 2) : null,
                'is_available' => (bool) $r->is_available,
                'rating' => $r->rating !== null ? round((float) $r->rating, 2) : null,
            ])->all(),
        ];
    }

    private function toolItemLookup(int $rid, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['error' => 'no item name given'];
        }
        $item = DB::table('menu_items')
            ->where('restaurant_id', $rid)
            ->where('name', 'like', '%' . $name . '%')
            ->first();
        if (! $item) {
            return ['error' => "no dish matching \"{$name}\""];
        }

        $units = function (int $d) use ($rid, $item) {
            return (int) DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.restaurant_id', $rid)
                ->where('order_items.menu_item_id', $item->id)
                ->whereNotIn('orders.status', ['cancelled'])
                ->where('orders.created_at', '>=', now()->subDays($d))
                ->sum('order_items.quantity');
        };

        return [
            'name' => $item->name,
            'price' => round((float) $item->price, 2),
            'discounted_price' => $item->discounted_price !== null ? round((float) $item->discounted_price, 2) : null,
            'is_available' => (bool) $item->is_available,
            'is_veg' => (bool) $item->is_veg,
            'rating' => $item->rating !== null ? round((float) $item->rating, 2) : null,
            'total_ratings' => (int) $item->total_ratings,
            'units_sold' => ['30d' => $units(30), '60d' => $units(60), '90d' => $units(90)],
        ];
    }

    private function toolMenuHealth(int $rid, Carbon $since): array
    {
        $agg = DB::table('menu_items')->where('restaurant_id', $rid)
            ->selectRaw('COUNT(*) total, SUM(is_available=1) available, MIN(price) min_price, MAX(price) max_price, AVG(price) avg_price')
            ->first();

        $soldIds = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.restaurant_id', $rid)
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', ['cancelled'])
            ->distinct()->pluck('order_items.menu_item_id');

        $zeroSales = DB::table('menu_items')->where('restaurant_id', $rid)
            ->where('is_available', 1)
            ->whereNotIn('id', $soldIds)
            ->limit(25)->pluck('name');

        return [
            'total_items' => (int) $agg->total,
            'available' => (int) $agg->available,
            'unavailable' => (int) $agg->total - (int) $agg->available,
            'price_range' => [round((float) $agg->min_price, 2), round((float) $agg->max_price, 2)],
            'avg_price' => round((float) $agg->avg_price, 2),
            'zero_sales_in_period' => $zeroSales->values()->all(),
        ];
    }

    private function toolPayoutsRecent(int $rid, int $limit): array
    {
        if (! Schema::hasTable('payouts')) {
            return ['cycles' => []];
        }
        $rows = DB::table('payouts')->where('restaurant_id', $rid)
            ->orderByDesc('created_at')->limit($limit)->get();

        return [
            'cycles' => $rows->map(fn ($p) => [
                'id' => (int) $p->id,
                'date' => Carbon::parse($p->created_at)->toDateString(),
                'status' => $p->status,
                'gross' => round((float) $p->gross_amount, 2),
                'platform_commission' => round((float) $p->platform_commission, 2),
                'commission_gst' => round((float) $p->gst_on_commission, 2),
                'gateway_fee' => round((float) $p->payment_gateway_fee, 2),
                'tds' => round((float) $p->tds_amount, 2),
                'tcs' => round((float) $p->tcs_amount, 2),
                'net' => round((float) ($p->net_amount ?: $p->amount), 2),
                'failure_reason' => $p->failure_reason,
            ])->all(),
        ];
    }

    private function toolPayoutExplain(int $rid, ?int $id): array
    {
        if (! Schema::hasTable('payouts')) {
            return ['error' => 'no payouts'];
        }
        $q = DB::table('payouts')->where('restaurant_id', $rid);
        $p = $id ? $q->where('id', $id)->first() : $q->orderByDesc('created_at')->first();
        if (! $p) {
            return ['error' => 'no matching payout'];
        }

        $orderIds = json_decode($p->order_ids ?? '[]', true);
        $breakdown = json_decode($p->breakdown ?? 'null', true);

        return [
            'id' => (int) $p->id,
            'date' => Carbon::parse($p->created_at)->toDateString(),
            'period' => [$p->period_start, $p->period_end],
            'status' => $p->status,
            'orders_in_cycle' => is_array($orderIds) ? count($orderIds) : null,
            'lines' => [
                'gross_sales' => round((float) $p->gross_amount, 2),
                'platform_commission' => round((float) $p->platform_commission, 2),
                'gst_on_commission' => round((float) $p->gst_on_commission, 2),
                'gateway_fee' => round((float) $p->payment_gateway_fee, 2),
                'restaurant_delivery_subsidy' => round((float) $p->restaurant_delivery_subsidy, 2),
                'deduction' => round((float) $p->deduction_amount, 2),
                'deduction_reason' => $p->deduction_reason,
                'tds' => round((float) $p->tds_amount, 2),
                'tds_section' => $p->tds_section,
                'tcs' => round((float) $p->tcs_amount, 2),
                'net_paid' => round((float) ($p->net_amount ?: $p->amount), 2),
            ],
            'breakdown_json' => $breakdown,
            'failure_reason' => $p->failure_reason,
        ];
    }

    private function toolReviewsSummary(int $rid, int $days): array
    {
        if (! Schema::hasTable('reviews')) {
            return ['error' => 'no reviews table'];
        }
        $since = now()->subDays($days);
        $base = DB::table('reviews')->where('restaurant_id', $rid)->where('created_at', '>=', $since);

        $agg = (clone $base)->selectRaw('COUNT(*) c, COALESCE(AVG(rating),0) avg')->first();
        $spread = (clone $base)->selectRaw('rating, COUNT(*) c')->groupBy('rating')->pluck('c', 'rating');
        $lows = (clone $base)->where('rating', '<=', 3)
            ->orderByDesc('created_at')->limit(8)
            ->get(['rating', 'comment', 'created_at']);

        return [
            'period_days' => $days,
            'count' => (int) $agg->c,
            'average_rating' => round((float) $agg->avg, 2),
            'rating_spread' => collect(range(1, 5))->mapWithKeys(fn ($s) => [$s => (int) ($spread[$s] ?? 0)])->all(),
            'recent_low_reviews' => $lows->map(fn ($r) => [
                'rating' => (int) $r->rating,
                'comment' => (string) $r->comment,
                'date' => Carbon::parse($r->created_at)->toDateString(),
            ])->all(),
        ];
    }

    private function toolPromotionsActive(int $rid): array
    {
        $out = ['promotions' => [], 'coupon_codes' => []];

        if (Schema::hasTable('promotions')) {
            $out['promotions'] = DB::table('promotions')
                ->where('restaurant_id', $rid)
                ->whereIn('status', ['active', 'scheduled', 'published', 'live'])
                ->orderByDesc('created_at')->limit(20)->get()
                ->map(fn ($p) => [
                    'title' => $p->title,
                    'type' => $p->promotion_type,
                    'status' => $p->status,
                    'starts_at' => $p->starts_at,
                    'ends_at' => $p->ends_at,
                    'used_count' => (int) $p->used_count,
                    'total_usage_limit' => $p->total_usage_limit !== null ? (int) $p->total_usage_limit : null,
                    'funding_type' => $p->funding_type ?? null,
                ])->all();
        }

        if (Schema::hasTable('promo_codes')) {
            $out['coupon_codes'] = DB::table('promo_codes')
                ->where('restaurant_id', $rid)
                ->where('is_active', 1)
                ->orderByDesc('created_at')->limit(20)->get()
                ->map(fn ($c) => [
                    'code' => $c->code,
                    'title' => $c->title,
                    'discount' => $c->discount_type === 'percentage'
                        ? "{$c->discount_value}%"
                        : "₹{$c->discount_value}",
                    'min_order' => $c->min_order_amount !== null ? round((float) $c->min_order_amount, 2) : null,
                    'used_count' => (int) $c->used_count,
                    'usage_limit' => $c->usage_limit !== null ? (int) $c->usage_limit : null,
                    'ends' => $c->end_date,
                ])->all();
        }

        return $out;
    }

    private function toolCancellations(int $rid, Carbon $since, int $days): array
    {
        $cancelled = (int) DB::table('orders')->where('restaurant_id', $rid)
            ->where('status', 'cancelled')->where('created_at', '>=', $since)->count();
        $all = (int) DB::table('orders')->where('restaurant_id', $rid)
            ->where('created_at', '>=', $since)->count();

        $reasons = DB::table('orders')->where('restaurant_id', $rid)
            ->where('status', 'cancelled')->where('created_at', '>=', $since)
            ->whereNotNull('cancellation_reason')
            ->selectRaw('cancellation_reason, COUNT(*) c')
            ->groupBy('cancellation_reason')->orderByDesc('c')->limit(8)->get();

        return [
            'period_days' => $days,
            'cancelled_orders' => $cancelled,
            'total_orders' => $all,
            'cancel_rate_pct' => $all > 0 ? round($cancelled * 100 / $all, 1) : 0.0,
            'top_reasons' => $reasons->map(fn ($r) => ['reason' => $r->cancellation_reason, 'count' => (int) $r->c])->all(),
        ];
    }

    // --------------------------------------------------------------- utilities

    private function pct(float $from, float $to): ?float
    {
        if ($from <= 0.0) {
            return $to > 0.0 ? 100.0 : 0.0;
        }
        return round(($to - $from) * 100 / $from, 1);
    }

    private function recentTurns(int $restaurantId, string $conversationId): array
    {
        if (! Schema::hasTable('restaurant_ai_messages')) {
            return [];
        }
        return DB::table('restaurant_ai_messages')
            ->where('restaurant_id', $restaurantId)
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')->limit(8)->get()
            ->reverse()
            ->map(fn ($r) => ['role' => $r->role, 'text' => $r->content])
            ->values()->all();
    }

    private function appName(): string
    {
        try {
            return (string) \App\Models\AppSetting::getValue('app_name', config('app.name', 'the app'));
        } catch (\Throwable $e) {
            return (string) config('app.name', 'the app');
        }
    }

    private function historyBlock(array $recent): string
    {
        $out = '';
        foreach ($recent as $t) {
            $who = ($t['role'] ?? '') === 'user' ? 'OWNER' : 'ASSISTANT';
            $out .= "{$who}: {$t['text']}\n";
        }
        return $out ?: "(none)\n";
    }

    private function persist(Restaurant $restaurant, ?int $userId, string $conversationId, string $role, string $content, array $plan, array $suggestions): void
    {
        if (! Schema::hasTable('restaurant_ai_messages') || $content === '') {
            return;
        }
        DB::table('restaurant_ai_messages')->insert([
            'conversation_id' => $conversationId,
            'restaurant_id' => $restaurant->id,
            'user_id' => $userId,
            'role' => $role,
            'content' => $content,
            'plan' => $plan ? json_encode($plan) : null,
            'suggestions' => $suggestions ? json_encode($suggestions) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
