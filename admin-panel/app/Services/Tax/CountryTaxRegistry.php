<?php

namespace App\Services\Tax;

use App\Models\AppSetting;
use App\Models\CountryTaxProfile;
use App\Models\Restaurant;
use App\Services\Tax\Contracts\TaxStrategy;
use App\Services\Tax\Strategies\AuSgGstTaxStrategy;
use App\Services\Tax\Strategies\EuVatTaxStrategy;
use App\Services\Tax\Strategies\GccVatTaxStrategy;
use App\Services\Tax\Strategies\GenericTaxStrategy;
use App\Services\Tax\Strategies\IndiaGstTaxStrategy;
use App\Services\Tax\Strategies\UkVatTaxStrategy;
use App\Services\Tax\Strategies\UsSalesTaxStrategy;

/**
 * Maps a resolved country code to its TaxStrategy — the same match()-table
 * pattern already used by App\Support\GatewayRegistry for payment gateways,
 * applied to tax/compliance instead.
 *
 * Add a new country's dedicated module by adding one line to STRATEGY_MAP;
 * a country with no dedicated class yet still works via GenericTaxStrategy
 * as long as an admin has activated a CountryTaxProfile row for it.
 */
class CountryTaxRegistry
{
    private const STRATEGY_MAP = [
        'IN' => IndiaGstTaxStrategy::class,
        'GB' => UkVatTaxStrategy::class,
        // GCC unified VAT framework -- one class, four member states that have
        // actually implemented VAT (Qatar/Kuwait have not, as of this build).
        'AE' => GccVatTaxStrategy::class,
        'SA' => GccVatTaxStrategy::class,
        'BH' => GccVatTaxStrategy::class,
        'OM' => GccVatTaxStrategy::class,
        // EU domestic VAT -- add another member state here as a one-line
        // addition once it's needed (see EuVatTaxStrategy::FALLBACK_RATES too).
        'DE' => EuVatTaxStrategy::class,
        'FR' => EuVatTaxStrategy::class,
        'ES' => EuVatTaxStrategy::class,
        'IT' => EuVatTaxStrategy::class,
        'NL' => EuVatTaxStrategy::class,
        'IE' => EuVatTaxStrategy::class,
        'US' => UsSalesTaxStrategy::class,
        // GST-named but VAT-structured: single national rate, no dual-tax split,
        // inclusive pricing, restaurant-level registration -- same mechanic, two markets.
        'AU' => AuSgGstTaxStrategy::class,
        'SG' => AuSgGstTaxStrategy::class,
    ];

    /**
     * The country code that actually governs this restaurant's tax: its own
     * country_code, else its branch's, else the platform-wide default.
     */
    public function resolveCountryCode(?Restaurant $restaurant): string
    {
        $code = $restaurant?->resolvedCountryCode();

        return strtoupper($code ?: (string) AppSetting::getValue('platform_country_code', 'IN'));
    }

    /**
     * Null means "no strategy for this country at all" — the caller should
     * fall back to the legacy flat TaxSetting calculation, exactly as it did
     * before any country-strategy concept existed.
     */
    public function resolveFor(?Restaurant $restaurant): ?TaxStrategy
    {
        $code = $this->resolveCountryCode($restaurant);

        if (isset(self::STRATEGY_MAP[$code])) {
            return app(self::STRATEGY_MAP[$code]);
        }

        $profile = CountryTaxProfile::where('country_code', $code)->where('is_active', true)->first();

        return $profile ? new GenericTaxStrategy($profile) : null;
    }

    /** All countries with a real, dedicated module (not just the generic fallback). */
    public function dedicatedCountryCodes(): array
    {
        return array_keys(self::STRATEGY_MAP);
    }

    /** Direct lookup by code, with no restaurant/order in play (admin UI use). */
    public function strategyForCode(string $code): ?TaxStrategy
    {
        $class = self::STRATEGY_MAP[strtoupper($code)] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * Drives the restaurant admin/branch form's dynamic per-country
     * registration block: for every ACTIVE country, what kind of
     * registration UI applies and (for the shared VAT-style kind) what to
     * label it. Three kinds, matching how liability actually varies:
     *  - 'india'   the existing hardcoded GSTIN/PAN/state-code block
     *              (unchanged -- India's own settings live on Business
     *              Settings/Taxation Setup, not here).
     *  - 'us'      no restaurant-level registration at all (marketplace-
     *              facilitator liability) -- just needs its own US state via
     *              the shared `state_code` column, relabeled.
     *  - 'vat'     every registration-gated single-rate strategy (UK/GCC/EU/
     *              AU-SG) AND a generic-fallback country both use the same
     *              `restaurants.is_vat_registered` + `vat_number` columns --
     *              genuinely the same shape (a flag + a registration number),
     *              only the tax name/label text differs.
     *
     * @return array<string, array{kind: string, name: string, tax_name: ?string, label: ?string}>
     */
    public function registrationUiMap(): array
    {
        $map = [];
        foreach (CountryTaxProfile::where('is_active', true)->get() as $profile) {
            $code = strtoupper($profile->country_code);
            if ($code === 'IN') {
                $map[$code] = ['kind' => 'india', 'name' => \App\Support\CountryTaxDefaults::nameFor($code), 'tax_name' => 'GST', 'label' => null];

                continue;
            }
            if ($code === 'US') {
                $map[$code] = ['kind' => 'us', 'name' => \App\Support\CountryTaxDefaults::nameFor($code), 'tax_name' => 'Sales Tax', 'label' => null];

                continue;
            }

            $strategy = $this->strategyForCode($code) ?? new GenericTaxStrategy($profile);
            $label = collect($strategy->registrationFields())->first()['label'] ?? ($strategy->taxName() . ' Registration Number');
            $map[$code] = [
                'kind' => 'vat',
                'name' => \App\Support\CountryTaxDefaults::nameFor($code),
                'tax_name' => $strategy->taxName(),
                'label' => $label,
            ];
        }

        return $map;
    }
}
