<?php

namespace App\Services\Tax;

use App\Models\Restaurant;
use App\Models\TdsDeducteeTotal;
use App\Models\User;

/**
 * Income-tax TDS deducted at settlement.
 *
 *  - Sec 194-O: on the restaurant's gross sale value (ex-GST) facilitated
 *    through the platform. FY ₹5,00,000 exemption for individual/HUF WITH PAN;
 *    no-PAN → capped 5%.
 *  - Sec 194-C: on driver payout (earnings − commission). Nil while single ≤
 *    ₹30,000 AND FY aggregate ≤ ₹1,00,000; the payout that crosses ₹1,00,000
 *    carries the arrears for the whole FY. No-PAN → 20%.
 *
 * Reads the deductee's running YTD from `tds_deductee_totals`; the caller
 * commits the new totals (see TaxLedgerService::recordPayoutDeduction()).
 */
class TdsService
{
    public function __construct(private readonly TaxConfig $config = new TaxConfig())
    {
    }

    public function restaurant194O(TaxEntity $r, float $grossExGst, string $fy): TaxDeduction
    {
        $grossExGst = round(max(0.0, $grossExGst), 2);

        if (! $this->config->tds194oEnabled() || ! $r->partyId || $grossExGst <= 0) {
            return TaxDeduction::none('194O', 'disabled', $grossExGst);
        }

        $priorGross = (float) ($this->ytd($r, $fy, '194O')?->gross_ytd ?? 0);
        $newGross = $priorGross + $grossExGst;
        $noPan = ! $r->hasPan();
        $isIndividual = $r->deducteeType !== 'company';
        $threshold = $this->config->tds194oThreshold();

        // Exemption only for individual/HUF who furnished a PAN.
        if ($isIndividual && ! $noPan && $threshold > 0 && $newGross <= $threshold) {
            return TaxDeduction::none('194O', 'below FY ₹' . number_format($threshold) . ' threshold', $grossExGst);
        }

        if ($isIndividual && ! $noPan && $threshold > 0) {
            $base = $this->config->tds194oAfterThresholdOnly()
                ? max(0.0, $newGross - max($priorGross, $threshold))
                : ($priorGross > $threshold ? $grossExGst : $newGross); // arrears on the crossing payout
        } else {
            $base = $grossExGst;
        }

        $rate = $noPan ? $this->config->tds194oNoPanRate() : $this->config->tds194oRate();
        $amount = round($base * $rate / 100, 2);

        return new TaxDeduction($amount, $rate, '194O', round($base, 2), $grossExGst, $noPan ? 'no PAN (206AA)' : null);
    }

    public function driver194C(TaxEntity $d, float $base, string $fy): TaxDeduction
    {
        $base = round(max(0.0, $base), 2);

        if (! $this->config->tds194cEnabled() || ! $d->partyId || $base <= 0) {
            return TaxDeduction::none('194C', 'disabled', $base);
        }

        $noPan = ! $d->hasPan();
        $rate = $noPan
            ? $this->config->tds194cNoPanRate()
            : ($d->deducteeType === 'company' ? $this->config->tds194cRateOther() : $this->config->tds194cRateIndividual());

        $single = $this->config->tds194cThresholdSingle();
        $annual = $this->config->tds194cThresholdAnnual();

        $priorGross = (float) ($this->ytd($d, $fy, '194C')?->gross_ytd ?? 0);
        $priorTds = (float) ($this->ytd($d, $fy, '194C')?->tds_ytd ?? 0);
        $newGross = $priorGross + $base;

        // Below both thresholds -> nil.
        if (! $noPan && $base <= $single && $newGross <= $annual) {
            return TaxDeduction::none('194C', 'below ₹' . number_format($single) . ' / ₹' . number_format($annual) . ' thresholds', $base);
        }

        // Once the annual aggregate is crossed, TDS is due on the whole FY
        // aggregate -> deduct on (newGross) minus what has already been taxed.
        $taxable = $priorTds > 0 ? $base : $newGross;
        $amount = round($taxable * $rate / 100, 2);

        return new TaxDeduction($amount, $rate, '194C', round($taxable, 2), $base, $noPan ? 'no PAN (206AA)' : null);
    }

    private function ytd(TaxEntity $party, string $fy, string $section): ?TdsDeducteeTotal
    {
        return TdsDeducteeTotal::where('party_type', $party->partyType ?: ($section === '194C' ? User::class : Restaurant::class))
            ->where('party_id', $party->partyId)
            ->where('fy', $fy)
            ->where('section', $section)
            ->first();
    }
}
