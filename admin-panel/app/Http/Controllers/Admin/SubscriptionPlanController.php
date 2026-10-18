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

        SubscriptionPlan::create($validated);

        return redirect()->route('admin.subscription-plans.index', ['audience' => $validated['audience']])
            ->with('success', 'Subscription plan created successfully!');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        return view('admin.subscription-plans.edit', ['plan' => $subscriptionPlan]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $this->validatePlan($request, $subscriptionPlan->id);

        if ($validated['name'] !== $subscriptionPlan->name) {
            $validated['slug'] = SubscriptionPlan::generateUniqueSlug($validated['name'], $subscriptionPlan->id);
        }

        $validated['is_active'] = $request->has('is_active');
        $validated['includes_stories'] = $request->has('includes_stories');

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
