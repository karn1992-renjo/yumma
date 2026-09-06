<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use App\Support\AmountInWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the customer order invoice (HTML view + dompdf PDF).
 *
 * Three shapes, decided by {@see invoiceType()}:
 *  - `tax_invoice`     GST on, supplier restaurant GST-registered -> CGST/SGST
 *                      split, HSN column, rate-wise summary (invoices.tax-invoice)
 *  - `bill_of_supply`  GST on, supplier not registered -> no tax lines
 *  - `receipt`         GST off -> the original simple invoice (invoices.order)
 *
 * Money is always read from the stored order columns / persisted
 * `tax_breakdown` so the document matches exactly what was charged.
 */
class InvoiceService
{
    public function pdf(Order $order)
    {
        $data = $this->data($order);
        $view = $data['meta']['invoice_type'] === 'receipt' ? 'invoices.order' : 'invoices.tax-invoice';

        return Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
    }

    public function filename(Order $order): string
    {
        return 'invoice-' . $order->order_number . '.pdf';
    }

    public function rawPdf(Order $order): string
    {
        return $this->pdf($order)->output();
    }

    public function invoiceType(Order $order): string
    {
        if (in_array($order->invoice_type, ['tax_invoice', 'bill_of_supply', 'receipt'], true)) {
            return $order->invoice_type;
        }

        $gstOn = (string) AppSetting::getValue('business_gst_enabled', '0') === '1';
        if (! $gstOn) {
            return 'receipt';
        }

        return $order->restaurant?->is_gst_registered ? 'tax_invoice' : 'bill_of_supply';
    }

    public function data(Order $order): array
    {
        $order->loadMissing('customer', 'restaurant');

        $symbol = AppSetting::sanitizedCurrencySymbol();
        $decimals = AppSetting::currencyDecimals();
        $money = static fn ($value) => $symbol . number_format((float) $value, $decimals);

        $type = $this->invoiceType($order);
        $isTax = $type === 'tax_invoice';

        $lines = is_array($order->items) ? $order->items : (json_decode((string) $order->items, true) ?: []);
        $gst = is_array($order->tax_breakdown) ? $order->tax_breakdown : null;
        $section95 = (bool) data_get($gst, 'section_9_5', false);

        // Index the persisted per-line GST rows by name so the printed item
        // table can show taxable value + rate without recomputing.
        $gstLineByName = collect($gst['lines'] ?? [])->keyBy(fn ($l) => strtolower(trim((string) ($l['name'] ?? ''))));

        $items = collect($lines)->map(function ($line) use ($gstLineByName) {
            $qty = (int) ($line['quantity'] ?? $line['qty'] ?? 1);
            $lineTotal = (float) ($line['total'] ?? $line['total_price'] ?? 0);
            $unit = (float) ($line['unit_price'] ?? $line['price'] ?? 0);
            if ($unit <= 0 && $qty > 0 && $lineTotal > 0) {
                $unit = $lineTotal / $qty;
            }
            if ($lineTotal <= 0) {
                $lineTotal = $unit * $qty;
            }

            $g = $gstLineByName->get(strtolower(trim((string) ($line['name'] ?? ''))));

            return [
                'name' => (string) ($line['name'] ?? $line['menu_item_name'] ?? data_get($line, 'menu_item.name') ?? 'Item'),
                'variant' => is_array($line['selected_variant'] ?? null) ? ($line['selected_variant']['name'] ?? null) : null,
                'addons' => collect($line['selected_add_ons'] ?? [])
                    ->map(fn ($a) => is_array($a) ? ($a['name'] ?? null) : $a)
                    ->filter()->implode(', ') ?: null,
                'qty' => $qty,
                'unit' => $unit,
                'total' => $lineTotal,
                'hsn' => $g['hsn'] ?? null,
                'gst_rate' => isset($g['rate']) ? (float) $g['rate'] : null,
                'taxable_value' => isset($g['taxable_value']) ? (float) $g['taxable_value'] : null,
                'cgst' => isset($g['cgst']) ? (float) $g['cgst'] : null,
                'sgst' => isset($g['sgst']) ? (float) $g['sgst'] : null,
            ];
        })->all();

        // Fee rows -- charges the customer paid, above the item subtotal.
        $rows = [];
        $rows[] = ['label' => 'Item subtotal', 'value' => (float) $order->subtotal, 'negative' => false];
        if ((float) $order->delivery_fee != 0.0) {
            $rows[] = ['label' => 'Delivery fee', 'value' => (float) $order->delivery_fee, 'negative' => false];
        }
        if ((float) ($order->surge_fee ?? 0) > 0) {
            $rows[] = ['label' => 'Surge fee', 'value' => (float) $order->surge_fee, 'negative' => false];
        }
        if ((float) ($order->platform_fee ?? 0) > 0) {
            $rows[] = ['label' => 'Platform fee', 'value' => (float) $order->platform_fee, 'negative' => false];
        }
        if ((float) ($order->tax ?? 0) > 0) {
            $rows[] = ['label' => $isTax ? 'GST' : 'Taxes & charges', 'value' => (float) $order->tax, 'negative' => false];
        }
        if ((float) ($order->tip_amount ?? 0) > 0) {
            $rows[] = ['label' => 'Delivery tip', 'value' => (float) $order->tip_amount, 'negative' => false];
        }
        if ((float) ($order->discount ?? 0) > 0) {
            $rows[] = ['label' => 'Discount', 'value' => (float) $order->discount, 'negative' => true];
        }

        $paidOnline = $order->payment_status === 'success';

        return [
            'order' => $order,
            'symbol' => $symbol,
            'decimals' => $decimals,
            'money' => $money,
            'items' => $items,
            'feeRows' => $rows,
            'gst' => $gst,
            'company' => $this->company(),
            'logo' => $this->logoDataUri(),
            'signature' => $this->assetDataUri((string) AppSetting::getValue('invoice_signature_image')),
            'amountInWords' => AmountInWords::inr((float) $order->total, ucfirst(strtolower(AppSetting::getValue('currency_name', 'Rupees')))),
            'section95' => $section95,
            'eco95' => $section95 ? [
                'cgst' => (float) ($order->eco_gst_food_cgst ?? data_get($gst, 'eco_food.cgst', 0)),
                'sgst' => (float) ($order->eco_gst_food_sgst ?? data_get($gst, 'eco_food.sgst', 0)),
                'rate' => (float) (AppSetting::getValue('gst_eco_food_rate', 5)),
            ] : null,
            'meta' => [
                'invoice_type' => $type,
                'title' => match ($type) {
                    'tax_invoice' => 'TAX INVOICE',
                    'bill_of_supply' => 'BILL OF SUPPLY',
                    default => 'INVOICE',
                },
                'invoice_no' => $order->invoice_number ?: ('INV-' . $order->order_number),
                'order_no' => $order->order_number,
                'date' => optional($order->invoice_date ?: $order->created_at)->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A'),
                'payment_method' => $this->paymentMethodLabel($order),
                'payment_status' => ucfirst((string) $order->payment_status),
                'order_status' => ucwords(str_replace('_', ' ', (string) $order->status)),
                'place_of_supply' => $order->place_of_supply ?: ($gst['place_of_supply'] ?? null),
                'reverse_charge' => 'No',
                'paid_label' => $paidOnline
                    ? $money($order->total) . ' paid online'
                    : ($order->isCashOnDelivery() ? $money($order->total) . ' to be collected on delivery' : $money(0) . ' paid'),
            ],
            'einvoice' => $order->einvoice_status === 'generated' ? [
                'irn' => $order->einvoice_irn,
                'ack_no' => $order->einvoice_ack_no,
                'acked_at' => optional($order->einvoice_acked_at)->format('d M Y, h:i A'),
                'qr' => $this->qrDataUri((string) $order->einvoice_qr),
            ] : null,
            'customer' => [
                'name' => $order->customer_name ?: ($order->customer?->name ?: 'Customer'),
                'phone' => $order->customer_phone ?: ($order->customer?->phone ?: ''),
                'email' => $order->customer?->email ?: '',
                'address' => $order->delivery_address ?: '',
                'gstin' => $order->buyer_gstin ?: '',
            ],
            // Supplier of record = the restaurant.
            'supplier' => [
                'name' => $order->restaurant?->name ?: '',
                'address' => trim(implode(', ', array_filter([
                    $order->restaurant?->address,
                    $order->restaurant?->city,
                    $order->restaurant?->state,
                    $order->restaurant?->pincode,
                ]))),
                'gstin' => $order->supplier_gstin ?: ($order->restaurant?->gstin ?: ''),
                'pan' => $order->restaurant?->pan ?: '',
                'state' => $order->restaurant?->state ?: '',
                'state_code' => $order->restaurant?->state_code ?: '',
                'fssai' => $order->restaurant?->fssai_license_number ?: '',
            ],
            // Kept for the legacy receipt template.
            'restaurant' => [
                'name' => $order->restaurant?->name ?: '',
                'address' => trim(implode(', ', array_filter([
                    $order->restaurant?->address,
                    $order->restaurant?->city,
                    $order->restaurant?->state,
                    $order->restaurant?->pincode,
                ]))),
                'gstin' => $order->restaurant?->fssai_license_number ?: '',
            ],
        ];
    }

    private function company(): array
    {
        $fallbackName = AppSetting::getValue('app_name')
            ?: AppSetting::getValue('site_name')
            ?: config('app.name', 'Swado');

        $get = static fn (string ...$keys) => (string) collect($keys)
            ->map(fn ($k) => AppSetting::getValue($k))
            ->first(fn ($v) => filled($v));

        return [
            'name' => $get('business_legal_name', 'invoice_company_name') ?: $fallbackName,
            'trade_name' => $get('business_trade_name'),
            'address' => $get('business_reg_address', 'invoice_company_address'),
            'email' => $get('business_email', 'invoice_company_email', 'support_email', 'contact_email'),
            'phone' => $get('business_phone', 'invoice_company_phone', 'contact_phone'),
            'gstin' => $get('business_gstin', 'invoice_company_tax_id'),
            'tax_id' => $get('business_gstin', 'invoice_company_tax_id'), // legacy receipt template
            'pan' => $get('business_pan'),
            'cin' => $get('business_cin'),
            'state' => $get('business_state'),
            'state_code' => $get('business_state_code'),
            'website' => $get('business_website', 'invoice_company_website'),
            'bank_name' => $get('invoice_bank_name'),
            'bank_account' => $get('invoice_bank_account'),
            'bank_ifsc' => $get('invoice_bank_ifsc'),
            'signatory' => $get('invoice_authorised_signatory'),
            'declaration' => $get('invoice_declaration'),
            'footer' => $get('invoice_terms', 'invoice_footer_note')
                ?: 'This is a system generated invoice and does not require a signature.',
        ];
    }

    private function paymentMethodLabel(Order $order): string
    {
        if ($order->isCashOnDelivery()) {
            return 'Cash on Delivery';
        }
        $method = trim((string) ($order->payment_method ?: $order->payment_gateway ?: 'Online'));

        return $method === '' ? 'Online' : ucwords(str_replace('_', ' ', $method));
    }

    /**
     * dompdf has remote assets disabled by default, so images must be
     * embedded. Returns a data: URI or null.
     */
    private function assetDataUri(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        try {
            if (str_starts_with($path, 'data:')) {
                return $path;
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                $bytes = @file_get_contents($path);
                $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
            } else {
                $disk = Storage::disk('public');
                if (! $disk->exists($path)) {
                    return null;
                }
                $bytes = $disk->get($path);
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            }

            if (! $bytes) {
                return null;
            }

            $mime = match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                default => 'image/jpeg',
            };

            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        } catch (\Throwable $e) {
            Log::info('Invoice asset could not be embedded.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function logoDataUri(): ?string
    {
        return $this->assetDataUri((string) AppSetting::getValue('app_logo'));
    }

    /**
     * The e-invoice "QR" is the signed-QR string. If the admin pasted a raw
     * data: URI image we use it directly; otherwise we render the string to a
     * QR PNG via a pure-PHP encoder if available, else return null (the IRN
     * text still prints).
     */
    private function qrDataUri(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, 'data:image')) {
            return $value;
        }
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            try {
                $png = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(140)->margin(0)->generate($value);

                return 'data:image/png;base64,' . base64_encode($png);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return null;
    }
}
