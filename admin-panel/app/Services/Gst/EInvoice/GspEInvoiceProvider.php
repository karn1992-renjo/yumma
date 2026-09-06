<?php

namespace App\Services\Gst\EInvoice;

use App\Models\AppSetting;
use App\Models\Order;
use App\Support\AmountInWords;

/**
 * Builds the standard IRP e-invoice JSON from the order + persisted
 * tax_breakdown. The actual submission to a GSP / the IRP is a deliberate
 * stub: it needs a chosen GSP vendor, their auth flow, and sandbox testing.
 * Configure the base URL + credentials in Settings -> Business and implement
 * `submit()` for your vendor to switch this on.
 */
class GspEInvoiceProvider implements EInvoiceProvider
{
    public function generate(Order $order): EInvoiceResult
    {
        $base = trim((string) AppSetting::getValue('einvoice_api_base'));
        $user = trim((string) AppSetting::getValue('einvoice_username'));

        if ($base === '' || $user === '') {
            return EInvoiceResult::failed('Configure a GSP: set the e-invoice API base URL and credentials in Settings → Business, then implement GspEInvoiceProvider::submit() for your provider.');
        }

        try {
            $payload = $this->buildPayload($order);

            return $this->submit($payload);
        } catch (\Throwable $e) {
            return EInvoiceResult::failed($e->getMessage());
        }
    }

    /**
     * @throws \RuntimeException until a vendor integration is added.
     */
    protected function submit(array $payload): EInvoiceResult
    {
        // TODO: vendor-specific auth + POST to {base}/eicore/v1.03/Invoice.
        // Parse Irn / SignedQRCode / AckNo / AckDt from the response.
        throw new \RuntimeException('Automated GSP submission is not implemented. Use "Manual entry" or add your provider in GspEInvoiceProvider::submit().');
    }

    protected function buildPayload(Order $order): array
    {
        $bd = is_array($order->tax_breakdown) ? $order->tax_breakdown : [];
        $decimals = 2;

        $itemList = [];
        $slNo = 1;
        foreach (array_merge($bd['lines'] ?? [], $bd['charges'] ?? []) as $row) {
            $taxable = (float) ($row['taxable_value'] ?? 0);
            $cgst = (float) ($row['cgst'] ?? 0);
            $sgst = (float) ($row['sgst'] ?? 0);
            $itemList[] = [
                'SlNo' => (string) $slNo++,
                'PrdDesc' => (string) ($row['name'] ?? $row['label'] ?? 'Item'),
                'IsServc' => 'Y',
                'HsnCd' => (string) ($row['hsn'] ?? '996331'),
                'Qty' => (float) ($row['qty'] ?? 1),
                'Unit' => 'NOS',
                'UnitPrice' => round($taxable / max(1, (int) ($row['qty'] ?? 1)), $decimals),
                'TotAmt' => round($taxable, $decimals),
                'AssAmt' => round($taxable, $decimals),
                'GstRt' => (float) ($row['rate'] ?? 0),
                'CgstAmt' => round($cgst, $decimals),
                'SgstAmt' => round($sgst, $decimals),
                'IgstAmt' => 0,
                'TotItemVal' => round($taxable + $cgst + $sgst, $decimals),
            ];
        }

        return [
            'Version' => '1.1',
            'TranDtls' => ['TaxSch' => 'GST', 'SupTyp' => 'B2C', 'RegRev' => 'N'],
            'DocDtls' => [
                'Typ' => 'INV',
                'No' => (string) ($order->invoice_number ?: $order->order_number),
                'Dt' => optional($order->invoice_date ?: $order->created_at)->format('d/m/Y'),
            ],
            'SellerDtls' => [
                'Gstin' => (string) ($order->supplier_gstin ?: $order->restaurant?->gstin),
                'LglNm' => (string) $order->restaurant?->name,
                'Addr1' => (string) $order->restaurant?->address,
                'Loc' => (string) $order->restaurant?->city,
                'Pin' => (int) preg_replace('/\D/', '', (string) $order->restaurant?->pincode) ?: 0,
                'Stcd' => (string) $order->restaurant?->state_code,
            ],
            'BuyerDtls' => [
                'Gstin' => 'URP',
                'LglNm' => (string) ($order->customer_name ?: 'Consumer'),
                'Pos' => (string) ($order->restaurant?->state_code ?: '27'),
                'Addr1' => (string) ($order->delivery_address ?: 'NA'),
                'Loc' => (string) ($order->restaurant?->city ?: 'NA'),
                'Pin' => (int) preg_replace('/\D/', '', (string) $order->restaurant?->pincode) ?: 0,
                'Stcd' => (string) ($order->restaurant?->state_code ?: '27'),
            ],
            'ItemList' => $itemList,
            'ValDtls' => [
                'AssVal' => round((float) ($bd['taxable_total'] ?? $order->subtotal), $decimals),
                'CgstVal' => round((float) ($order->cgst_amount ?? 0), $decimals),
                'SgstVal' => round((float) ($order->sgst_amount ?? 0), $decimals),
                'IgstVal' => 0,
                'TotInvVal' => round((float) $order->total, $decimals),
            ],
            '_amount_in_words' => AmountInWords::inr((float) $order->total),
        ];
    }
}
