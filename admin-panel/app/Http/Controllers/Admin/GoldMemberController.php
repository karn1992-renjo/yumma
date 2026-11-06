<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\GoldSubscriptionService;
use App\Services\MediaStorage;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-side manual Gold membership management -- separate from the
 * customer-facing paid flow in Api\CustomerGoldController. Lets an admin
 * grant a user free/comped Gold membership by mobile number (support
 * gestures, promos, staff accounts) without that user ever going through a
 * real gateway checkout. A row created here is identical in shape to a
 * paid one (same customer_subscriptions table, same waiver logic in
 * Api\OrderController::buildPricingSummary()) except gateway stays NULL --
 * that's the only signal that distinguishes "admin granted" from "customer
 * paid" when listing members below.
 */
class GoldMemberController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerSubscription::with(['user', 'plan'])
            ->whereIn('status', ['trialing', 'active', 'past_due', 'pending_authorization']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $members = $query->latest()->paginate(20)->withQueryString();

        $plans = SubscriptionPlan::where('audience', 'customer')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $stats = [
            'total_active' => CustomerSubscription::whereIn('status', ['trialing', 'active', 'past_due'])->count(),
            'admin_granted' => CustomerSubscription::whereIn('status', ['trialing', 'active', 'past_due'])->whereNull('gateway')->count(),
        ];

        $shareTemplatePath = AppSetting::getValue('gold_share_template_image');
        $shareTemplateUrl = $shareTemplatePath ? MediaStorage::url($shareTemplatePath) : null;

        return view('admin.gold-members.index', compact('members', 'plans', 'stats', 'shareTemplateUrl'));
    }

    /**
     * The background image behind the "You saved ₹X with Gold" card a
     * customer can share after an order where Gold waived the delivery fee
     * -- see Api\CustomerGoldController::current()'s gold_share_template_url
     * and the customer app's order confirmation screen.
     */
    public function updateShareTemplate(Request $request)
    {
        $validated = $request->validate([
            'share_template_image' => ['required', 'image', 'max:4096'],
        ]);

        $existing = AppSetting::getValue('gold_share_template_image');
        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }

        $path = $request->file('share_template_image')->store('gold', 'public');

        AppSetting::updateOrCreate(
            ['key' => 'gold_share_template_image'],
            ['value' => $path, 'type' => 'string']
        );
        cache()->forget('app_settings');

        return redirect()->route('admin.gold-members.index')
            ->with('success', 'Gold share template updated.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::where('audience', 'customer')->findOrFail($validated['subscription_plan_id']);

        $normalizedPhone = PhoneNumber::normalize(
            $validated['phone'],
            AppSetting::getValue('default_mobile_country_code', '+91')
        );

        $user = User::where('phone', $normalizedPhone)->first();

        if (! $user) {
            return back()->withInput()->with('error', "No user found with mobile number {$validated['phone']}. They need to have signed up in the app first.");
        }

        $existing = CustomerSubscription::where('user_id', $user->id)
            ->whereIn('status', ['pending_authorization', 'trialing', 'active', 'past_due'])
            ->first();

        if ($existing) {
            return back()->withInput()->with('error', "{$user->name} already has a Gold membership (status: {$existing->status}). Revoke it first if you want to replace it.");
        }

        app(GoldSubscriptionService::class)->grantManually(
            $user,
            $plan,
            'Granted by admin ('.($request->user()->name ?? 'admin').')'
        );

        return redirect()->route('admin.gold-members.index')
            ->with('success', "{$user->name} is now a Gold member.");
    }

    public function revoke(CustomerSubscription $goldMember, GoldSubscriptionService $goldService)
    {
        $goldService->cancel($goldMember);

        return redirect()->route('admin.gold-members.index')
            ->with('success', 'Gold membership revoked.');
    }
}
