<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request)
    {
        $audience = in_array($request->get('audience'), ['restaurant', 'customer'], true)
            ? $request->get('audience')
            : 'restaurant';

        $plans = SubscriptionPlan::where('audience', $audience)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => SubscriptionPlan::where('audience', $audience)->count(),
            'active' => SubscriptionPlan::where('audience', $audience)->where('is_active', true)->count(),
        ];

        return view('admin.subscription-plans.index', compact('plans', 'stats', 'audience'));
    }

    public function create(Request $request)
    {
        $audience = in_array($request->get('audience'), ['restaurant', 'customer'], true)
            ? $request->get('audience')
            : 'restaurant';

        return view('admin.subscription-plans.create', compact('audience'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePlan($request);

        $validated['slug'] = SubscriptionPlan::generateUniqueSlug($validated['name']);
        $validated['is_active'] = $request->has('is_active');
        $validated['includes_stories'] = $request->has('includes_stories');
        $validated['story_menu_link'] = $request->boolean('story_menu_link');

        SubscriptionPlan::create($validated);

        return redirect()->route('admin.subscription-plans.index', ['audience' => $validated['audience']])
            ->with('success', 'Subscription plan created successfully!');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        // A stories plan is edited in its own form.
        if ($subscriptionPlan->audience === 'restaurant' && $subscriptionPlan->scope === 'stories_addon') {
            return redirect()->route('admin.subscription-plans.stories.edit', $subscriptionPlan);
        }

        return view('admin.subscription-plans.edit', ['plan' => $subscriptionPlan]);
    }

    public function createStories()
    {
        return view('admin.subscription-plans.stories');
    }

    public function storeStories(Request $request)
    {
        $data = $this->storiesPlanData($request);
        $data['slug'] = SubscriptionPlan::generateUniqueSlug($data['name']);

        SubscriptionPlan::create($data);

        return redirect()->route('admin.subscription-plans.index', ['audience' => 'restaurant'])->with('success', 'Stories plan created.');
    }

    public function editStories(SubscriptionPlan $subscriptionPlan)
    {
        abort_unless($subscriptionPlan->audience === 'restaurant' && $subscriptionPlan->scope === 'stories_addon', 404);

        return view('admin.subscription-plans.stories', ['plan' => $subscriptionPlan]);
    }

    public function updateStories(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        abort_unless($subscriptionPlan->audience === 'restaurant' && $subscriptionPlan->scope === 'stories_addon', 404);

        $data = $this->storiesPlanData($request, $subscriptionPlan->id);
        if ($data['name'] !== $subscriptionPlan->name) {
            $data['slug'] = SubscriptionPlan::generateUniqueSlug($data['name'], $subscriptionPlan->id);
        }
        $subscriptionPlan->update($data);

        return redirect()->route('admin.subscription-plans.index', ['audience' => 'restaurant'])->with('success', 'Stories plan updated.');
    }

    /** Only what a pay-as-you-go stories plan needs; everything else is fixed here so the plan always behaves the same. */
    private function storiesPlanData(Request $request, ?int $ignoreId = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('subscription_plans', 'name')->ignore($ignoreId)],
            'duration_days' => 'required|integer|min:1|max:3650',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0|gt:price',
            'currency' => 'nullable|string|size:3',
            'sort_order' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:1000',
            'stories_per_day' => 'nullable|integer|min:1|max:1000',
            'story_duration_hours' => 'nullable|integer|min:1|max:720',
            'story_menu_link' => 'nullable|boolean',
        ]);

        return [
            'audience' => 'restaurant',
            'scope' => 'stories_addon',
            'name' => $v['name'],
            'billing_period' => 'monthly',               // unused: the plan is priced per validity_days
            'validity_days' => (int) $v['duration_days'],
            'price' => $v['price'],
            'compare_at_price' => $v['compare_at_price'] ?? null,
            'currency' => strtoupper($v['currency'] ?? '') ?: (\App\Models\AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
            'order_limit' => null,
            'menu_item_limit' => null,
            'trial_days' => 0,
            'includes_stories' => true,
            'stories_per_day' => $v['stories_per_day'] ?? null,
            'story_duration_hours' => $v['story_duration_hours'] ?? null,
            'story_menu_link' => $request->boolean('story_menu_link'),
            'sort_order' => (int) ($v['sort_order'] ?? 0),
            'description' => $v['description'] ?? null,
            'is_active' => $request->has('is_active'),
        ];
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $this->validatePlan($request, $subscriptionPlan->id);

        if ($validated['name'] !== $subscriptionPlan->name) {
            $validated['slug'] = SubscriptionPlan::generateUniqueSlug($validated['name'], $subscriptionPlan->id);
        }

        $validated['is_active'] = $request->has('is_active');
        $validated['includes_stories'] = $request->has('includes_stories');
        $validated['story_menu_link'] = $request->boolean('story_menu_link');

        $subscriptionPlan->update($validated);

        return redirect()->route('admin.subscription-plans.index', ['audience' => $subscriptionPlan->audience])
            ->with('success', 'Subscription plan updated successfully!');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        $hasSubscribers = $subscriptionPlan->subscriptions()->exists()
            || CustomerSubscription::where('subscription_plan_id', $subscriptionPlan->id)->exists();

        if ($hasSubscribers) {
            return back()->with('error', 'This plan has restaurants/customers subscribed to it (past or present) and cannot be deleted. Deactivate it instead.');
        }

        $audience = $subscriptionPlan->audience;
        $subscriptionPlan->delete();

        return redirect()->route('admin.subscription-plans.index', ['audience' => $audience])
            ->with('success', 'Subscription plan deleted successfully!');
    }

    public function toggleStatus(SubscriptionPlan $subscriptionPlan)
    {
        $subscriptionPlan->update(['is_active' => ! $subscriptionPlan->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $subscriptionPlan->is_active,
            'message' => $subscriptionPlan->is_active ? 'Plan activated!' : 'Plan deactivated!',
        ]);
    }

    private function validatePlan(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'audience' => 'required|in:restaurant,customer',
            'scope' => 'required|in:full_plan,stories_addon',
            'name' => ['required', 'string', 'max:150', Rule::unique('subscription_plans', 'name')->ignore($ignoreId)],
            'billing_period' => 'required|in:monthly,quarterly,yearly',
            'validity_days' => 'nullable|integer|min:1|max:3650',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0|gt:price',
            'currency' => 'nullable|string|size:3',
            'order_limit' => 'nullable|integer|min:1',
            'menu_item_limit' => 'nullable|integer|min:1',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'sort_order' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:1000',
        ]);
    }
}
