<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CountryTaxProfile;
use App\Services\Tax\CountryTaxRegistry;
use App\Support\CountryTaxDefaults;
use App\Support\UsStates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * "Country & Tax" settings: the platform-wide default operating country, and
 * the list of CountryTaxProfile rows an admin has activated. A country whose
 * module is rich enough to need its own full page (currently only India, via
 * admin.settings.taxation-setup) is managed there instead; every other
 * country — including dedicated-but-simple ones like UK VAT — is configured
 * right here with the same rate/currency/label editor.
 */
class CountryTaxController extends Controller
{
    public function index()
    {
        $profiles = CountryTaxProfile::orderByDesc('is_active')->orderBy('country_name')->get();
        $registry = app(CountryTaxRegistry::class);
        $dedicatedCodes = $registry->dedicatedCountryCodes();
        $customSettingsRoutes = $this->customSettingsRoutes($registry, $dedicatedCodes);
        $countryDefaults = CountryTaxDefaults::COUNTRIES;
        $platformDefault = strtoupper((string) AppSetting::getValue('platform_country_code', 'IN'));

        return view('admin.settings.country-tax', compact('profiles', 'dedicatedCodes', 'customSettingsRoutes', 'countryDefaults', 'platformDefault'));
    }

    public function updatePlatformDefault(Request $request)
    {
        $validated = $request->validate([
            'platform_country_code' => ['required', 'string', 'size:2'],
        ]);

        AppSetting::updateOrCreate(
            ['key' => 'platform_country_code'],
            ['value' => strtoupper($validated['platform_country_code']), 'type' => 'string']
        );
        Cache::forget('app_settings');

        return redirect()->route('admin.settings.country-tax')->with('success', 'Platform default country updated.');
    }

    public function storeProfile(Request $request)
    {
        $validated = $this->validateProfile($request);

        $strategy = app(CountryTaxRegistry::class)->strategyForCode($validated['country_code']);
        if ($strategy?->customSettingsRoute()) {
            return redirect()->route($strategy->customSettingsRoute())
                ->with('success', $validated['country_name'] . ' has its own dedicated settings page — configure it here instead.');
        }

        CountryTaxProfile::updateOrCreate(
            ['country_code' => strtoupper($validated['country_code'])],
            [
                'country_name' => $validated['country_name'],
                'currency_code' => strtoupper($validated['currency_code']),
                'tax_name' => $validated['tax_name'],
                'is_active' => $request->boolean('is_active', true),
                'config' => ['rate' => $validated['rate'], 'taxable_base_includes_fees' => $request->boolean('taxable_base_includes_fees')],
            ]
        );

        return redirect()->route('admin.settings.country-tax')->with('success', 'Country added.');
    }

    public function updateProfile(Request $request, CountryTaxProfile $profile)
    {
        $strategy = app(CountryTaxRegistry::class)->strategyForCode($profile->country_code);
        if ($strategy?->customSettingsRoute()) {
            abort(422, 'This country has its own dedicated settings page — manage it there instead.');
        }

        $validated = $this->validateProfile($request, $profile);

        $profile->update([
            'country_name' => $validated['country_name'],
            'currency_code' => strtoupper($validated['currency_code']),
            'tax_name' => $validated['tax_name'],
            'is_active' => $request->boolean('is_active', true),
            'config' => ['rate' => $validated['rate'], 'taxable_base_includes_fees' => $request->boolean('taxable_base_includes_fees')],
        ]);

        return redirect()->route('admin.settings.country-tax')->with('success', 'Country updated.');
    }

    public function toggleProfile(CountryTaxProfile $profile)
    {
        $profile->update(['is_active' => ! $profile->is_active]);

        return redirect()->route('admin.settings.country-tax')
            ->with('success', $profile->country_name . ' ' . ($profile->is_active ? 'activated' : 'deactivated') . '.');
    }

    /**
     * US sales tax's own settings page — its config genuinely doesn't fit the
     * generic single-rate editor (a per-state rates map, not one flat rate),
     * so it gets a dedicated page the same way India does, just much smaller.
     */
    public function usSalesTax()
    {
        $profile = CountryTaxProfile::firstOrCreate(
            ['country_code' => 'US'],
            ['country_name' => 'United States', 'currency_code' => 'USD', 'tax_name' => 'Sales Tax', 'is_active' => true, 'config' => ['rates' => []]]
        );
        $rates = (array) ($profile->configValue('rates') ?? []);
        $states = UsStates::STATES;
        $suggestedRates = UsStates::SUGGESTED_RATES;

        return view('admin.settings.us-sales-tax', compact('profile', 'rates', 'states', 'suggestedRates'));
    }

    public function updateUsSalesTax(Request $request)
    {
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'rates' => ['nullable', 'array'],
            'rates.*' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        $rates = [];
        foreach ($validated['rates'] ?? [] as $state => $rate) {
            if (! array_key_exists(strtoupper($state), UsStates::STATES) || $rate === null || $rate === '') {
                continue;
            }
            $rates[strtoupper($state)] = round((float) $rate, 3);
        }

        CountryTaxProfile::updateOrCreate(
            ['country_code' => 'US'],
            [
                'country_name' => 'United States',
                'currency_code' => 'USD',
                'tax_name' => 'Sales Tax',
                'is_active' => $request->boolean('is_active', true),
                'config' => ['rates' => $rates],
            ]
        );

        return redirect()->route('admin.settings.us-sales-tax')->with('success', count($rates) . ' state rate(s) saved.');
    }

    /** @return array<string, string> country_code => route name, only for dedicated countries that actually have one */
    private function customSettingsRoutes(CountryTaxRegistry $registry, array $dedicatedCodes): array
    {
        $routes = [];
        foreach ($dedicatedCodes as $code) {
            $route = $registry->strategyForCode($code)?->customSettingsRoute();
            if ($route) {
                $routes[$code] = $route;
            }
        }

        return $routes;
    }

    private function validateProfile(Request $request, ?CountryTaxProfile $profile = null): array
    {
        return $request->validate([
            'country_code' => [
                'required', 'string', 'size:2',
                Rule::unique('country_tax_profiles', 'country_code')->ignore($profile?->id),
            ],
            'country_name' => ['required', 'string', 'max:80'],
            'currency_code' => ['required', 'string', 'size:3'],
            'tax_name' => ['required', 'string', 'max:40'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'taxable_base_includes_fees' => ['nullable', 'boolean'],
        ]);
    }
}
