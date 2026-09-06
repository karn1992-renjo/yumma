<?php

namespace App\Services\Tax;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;

/**
 * Typed accessors over the Business Settings tax block.
 *
 * A tax service only activates when the business is actually **registered** for
 * it -- the toggle alone is not enough. If the required identifier
 * (GSTIN / TAN) is missing or malformed the service stays off and the platform
 * bills normally, with no tax.
 */
class TaxConfig
{
    public const GSTIN_RE = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
    public const PAN_RE = '/^[A-Z]{5}[0-9]{4}[A-Z]$/';
    public const TAN_RE = '/^[A-Z]{4}[0-9]{5}[A-Z]$/';

    public static function validGstin(?string $v): bool
    {
        return $v !== null && (bool) preg_match(self::GSTIN_RE, strtoupper(trim($v)));
    }

    public static function validPan(?string $v): bool
    {
        return $v !== null && (bool) preg_match(self::PAN_RE, strtoupper(trim($v)));
    }

    public static function validTan(?string $v): bool
    {
        return $v !== null && (bool) preg_match(self::TAN_RE, strtoupper(trim($v)));
    }

    /** Derive the 2-digit GST state code from a GSTIN. */
    public static function stateCodeFromGstin(?string $gstin): ?string
    {
        return self::validGstin($gstin) ? substr(strtoupper(trim($gstin)), 0, 2) : null;
    }

    /** Derive the PAN embedded in a GSTIN (characters 3-12). */
    public static function panFromGstin(?string $gstin): ?string
    {
        return self::validGstin($gstin) ? substr(strtoupper(trim($gstin)), 2, 10) : null;
    }

    public function gstin(): ?string
    {
        $v = trim((string) (AppSetting::getValue('business_gstin') ?: AppSetting::getValue('invoice_company_tax_id')));

        return $v !== '' ? strtoupper($v) : null;
    }

    /** Is the platform GST-registered (valid GSTIN present)? */
    public function gstRegistered(): bool
    {
        return self::validGstin($this->gstin());
    }

    /** Is the platform registered as a TDS deductor (valid TAN)? */
    public function tdsRegistered(): bool
    {
        return self::validTan($this->tan());
    }

    /** Is the platform registered as a GST TCS collector? */
    public function tcsRegistered(): bool
    {
        $tcsGstin = trim((string) (AppSetting::getValue('einvoice_gstin') ?: $this->gstin()));

        return self::validGstin($tcsGstin)
            && (string) AppSetting::getValue('gst_tcs_registered', '0') === '1';
    }

    /**
     * GST invoicing / 9(5) is live only when the admin has opted in AND the
     * business actually holds a valid GSTIN.
     */
    public function gstEnabled(): bool
    {
        return (string) AppSetting::getValue('business_gst_enabled', '0') === '1'
            && $this->gstRegistered();
    }

    public function section95Mode(): bool
    {
        return $this->gstEnabled() && (string) AppSetting::getValue('gst_9_5_mode', '1') === '1';
    }

    public function ecoFoodRate(): float
    {
        return (float) AppSetting::getValue('gst_eco_food_rate', 5);
    }

    public function serviceRate(): float
    {
        return (float) AppSetting::getValue('gst_service_rate', 18);
    }

    public function commissionGstRate(): float
    {
        // Reuses the long-standing "GST on platform commission" setting.
        $rate = (float) (AppSetting::getValue('gst_rate') ?? 18);

        return $rate <= 1 ? $rate * 100 : $rate;
    }

    /* ---- GST TCS (Sec 52) ---- */

    public function tcsEnabled(): bool
    {
        return $this->gstEnabled()
            && (string) AppSetting::getValue('gst_tcs_enabled', '0') === '1'
            && $this->tcsRegistered();
    }

    public function tcsRate(): float
    {
        return (float) AppSetting::getValue('gst_tcs_rate', 0.5);
    }

    /* ---- Income-tax TDS ---- */

    public function tan(): string
    {
        return trim((string) AppSetting::getValue('business_tan', ''));
    }

    public function tds194oEnabled(): bool
    {
        return $this->gstEnabled()
            && (string) AppSetting::getValue('tds_194o_enabled', '0') === '1'
            && $this->tdsRegistered();
    }

    public function tds194oRate(): float
    {
        return (float) AppSetting::getValue('tds_194o_rate', 0.1);
    }

    public function tds194oNoPanRate(): float
    {
        return (float) AppSetting::getValue('tds_194o_nopan_rate', 5);
    }

    public function tds194oThreshold(): float
    {
        return (float) AppSetting::getValue('tds_194o_threshold', 500000);
    }

    public function tds194oAfterThresholdOnly(): bool
    {
        return (string) AppSetting::getValue('tds_194o_after_threshold_only', '1') === '1';
    }

    public function tds194cEnabled(): bool
    {
        return $this->gstEnabled()
            && (string) AppSetting::getValue('tds_194c_enabled', '0') === '1'
            && $this->tdsRegistered();
    }

    public function tds194cRateIndividual(): float
    {
        return (float) AppSetting::getValue('tds_194c_rate_individual', 1);
    }

    public function tds194cRateOther(): float
    {
        return (float) AppSetting::getValue('tds_194c_rate_other', 2);
    }

    public function tds194cNoPanRate(): float
    {
        return (float) AppSetting::getValue('tds_194c_nopan_rate', 20);
    }

    public function tds194cThresholdSingle(): float
    {
        return (float) AppSetting::getValue('tds_194c_threshold_single', 30000);
    }

    public function tds194cThresholdAnnual(): float
    {
        return (float) AppSetting::getValue('tds_194c_threshold_annual', 100000);
    }

    /* ---- Gig-worker welfare cess ---- */

    public function gigCessEnabled(): bool
    {
        return (string) AppSetting::getValue('gig_welfare_cess_enabled', '0') === '1'
            && $this->gigCessRate() > 0;
    }

    public function gigCessRate(): float
    {
        return max(0.0, (float) AppSetting::getValue('gig_welfare_cess_rate', 0));
    }

    /** order_value | driver_payout */
    public function gigCessBase(): string
    {
        return AppSetting::getValue('gig_welfare_cess_base', 'order_value') === 'driver_payout'
            ? 'driver_payout' : 'order_value';
    }

    /** platform | driver -- who bears the cess in the settlement. */
    public function gigCessBorneBy(): string
    {
        return AppSetting::getValue('gig_cess_borne_by', 'platform') === 'driver' ? 'driver' : 'platform';
    }

    /* ---- Business entity ---- */

    public function entityType(): string
    {
        $t = (string) AppSetting::getValue('business_entity_type', 'pvt_ltd');

        return in_array($t, ['pvt_ltd', 'opc', 'llp', 'partnership', 'proprietorship'], true) ? $t : 'pvt_ltd';
    }

    public function hasEmployees(): bool
    {
        return (string) AppSetting::getValue('business_has_employees', '0') === '1';
    }

    /* ---- General ledger ---- */

    public function accountingEnabled(): bool
    {
        return (string) AppSetting::getValue('accounting_enabled', '0') === '1';
    }

    /* ---- Financial year ---- */

    public function fyStartMonth(): int
    {
        return max(1, min(12, (int) AppSetting::getValue('tax_financial_year_start_month', 4)));
    }

    public function fy(Carbon|string|null $date = null): string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date ?: now());
        $start = $this->fyStartMonth();
        $startYear = $date->month >= $start ? $date->year : $date->year - 1;

        return $startYear . '-' . str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT);
    }

    public function period(Carbon|string|null $date = null): string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date ?: now());

        return $date->format('Y-m');
    }
}
