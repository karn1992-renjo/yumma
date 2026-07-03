<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PayoutSetting;
use App\Services\PayoutGatewayService;
use App\Support\GatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PayoutSettingsController extends Controller
{
    public function edit()
    {
        $settings = PayoutSetting::all()->keyBy('gateway');
        $activeGateway = PayoutSetting::activeGateway();
        $gatewayOptions = GatewayRegistry::payoutProviders();
        $capabilityMatrix = GatewayRegistry::payoutCapabilityMatrix();

        return view('admin.settings.payout', compact('settings', 'activeGateway', 'gatewayOptions', 'capabilityMatrix'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'active_gateway' => 'required|in:' . implode(',', array_keys(GatewayRegistry::payoutProviders())),
            'auto_generate_enabled' => 'nullable|boolean',
            'auto_process_enabled' => 'nullable|boolean',
            'schedule_frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'schedule_day' => 'nullable|string|max:20',
            'minimum_payout_amount' => 'required|numeric|min:0',
            'credentials' => 'nullable|array',
            'webhook_config' => 'nullable|array',
            'options' => 'nullable|array',
        ]);

        if (
            GatewayRegistry::usesExternalManualSettlement($validated['active_gateway'])
            && $request->boolean('auto_process_enabled')
        ) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'auto_process_enabled' => GatewayRegistry::providerLabel($validated['active_gateway'], payout: true) . ' supports manual settlement tracking only in this app. Disable auto process or switch to RazorpayX, Stripe Connect, Cashfree, or Paystack.',
                ]);
        }

        PayoutSetting::query()->update(['is_active' => false]);
        PayoutSetting::updateOrCreate(
            ['gateway' => $validated['active_gateway']],
            [
                'is_active' => true,
                'auto_generate_enabled' => $request->boolean('auto_generate_enabled'),
                'auto_process_enabled' => $request->boolean('auto_process_enabled'),
                'schedule_frequency' => $validated['schedule_frequency'],
                'schedule_day' => $validated['schedule_day'] ?? null,
                'minimum_payout_amount' => $validated['minimum_payout_amount'],
                'credentials' => $validated['credentials'] ?? [],
                'webhook_config' => $validated['webhook_config'] ?? [],
                'options' => $validated['options'] ?? [],
            ]
        );

        AppSetting::setValue('payout_gateway_provider', $validated['active_gateway']);
        AppSetting::setValue('payout_frequency', $validated['schedule_frequency']);
        AppSetting::setValue('payout_day', $validated['schedule_day'] ?? '');
        AppSetting::setValue('minimum_payout_amount', $validated['minimum_payout_amount']);

        foreach (($validated['credentials'] ?? []) as $key => $value) {
            if ($value !== null && $value !== '') {
                AppSetting::setValue($key, $value);
            }
        }

        Cache::forget('app_settings');

        return redirect()->back()->with('success', 'Payout settings updated.');
    }

    public function balance(string $gateway, PayoutGatewayService $gatewayService)
    {
        return response()->json(['success' => true, 'data' => $gatewayService->balance($gateway)]);
    }
}
