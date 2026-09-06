<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\NotificationTemplate;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the customer-facing order confirmation email. The subject + HTML body
 * come from the editable `email.order_confirmation` NotificationTemplate
 * (Admin -> Email Templates); a hardcoded fallback here keeps a missing or
 * disabled template from silently dropping the mail.
 */
class OrderEmailService
{
    public const TEMPLATE_KEY = 'email.order_confirmation';

    public function sendOrderConfirmation(Order $order): void
    {
        $order->loadMissing('customer', 'restaurant');

        if ($order->confirmation_email_sent_at !== null) {
            return;
        }

        $email = trim((string) ($order->customer?->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('Order confirmation email skipped: no valid customer email.', [
                'order_id' => $order->id,
            ]);
            return;
        }

        $vars = $this->variables($order);

        [$subject, $body] = $this->render($vars);

        $name = trim((string) ($order->customer?->name ?? '')) ?: null;

        // Auto-generated invoice PDF -- attached when it renders, but a PDF
        // failure must not stop the confirmation email itself.
        $invoice = null;
        try {
            $invoiceService = app(InvoiceService::class);
            // Allocate a sequential invoice number once, before rendering, so
            // GST tax invoices carry a proper series. Receipts are skipped.
            if ($invoiceService->invoiceType($order) !== 'receipt') {
                app(\App\Services\Gst\InvoiceNumberService::class)->allocate($order);
                $order->refresh();
            }
            $invoice = [
                'data' => $invoiceService->rawPdf($order),
                'name' => $invoiceService->filename($order),
            ];
        } catch (\Throwable $e) {
            Log::warning('Order invoice PDF generation failed.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            Mail::html($body, function ($message) use ($email, $name, $subject, $invoice) {
                $message->to($email, $name)->subject($subject);
                if ($invoice) {
                    $message->attachData($invoice['data'], $invoice['name'], ['mime' => 'application/pdf']);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Order confirmation email failed to send.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            return;
        }

        $order->forceFill(['confirmation_email_sent_at' => now()])->saveQuietly();
    }

    /**
     * Render the template (or fallback) for a given variable set.
     *
     * @return array{0: string, 1: string} [subject, htmlBody]
     */
    public function render(array $vars): array
    {
        $rendered = NotificationTemplate::renderFor(
            self::TEMPLATE_KEY,
            $vars,
            $this->fallbackBody(),
            $this->fallbackSubject()
        );

        return [
            $rendered['title'] ?: $this->interpolateFallbackSubject($vars),
            $rendered['body'],
        ];
    }

    /**
     * Sample values used by the admin editor preview + "send test" action so
     * the same code path renders in both places.
     */
    public function sampleVariables(): array
    {
        $currency = AppSetting::sanitizedCurrencySymbol();

        return [
            'customer_name' => 'Riya Sharma',
            'order_number' => 'ORD20260831XXXX',
            'restaurant_name' => 'Tasty Bites',
            'order_status' => 'Confirmed',
            'order_date' => now()->format('d M Y, h:i A'),
            'payment_method' => 'Cash on Delivery',
            'delivery_address' => '12, MG Road, Bengaluru 560001',
            'items_table' => $this->itemsTable([
                ['name' => 'Paneer Butter Masala', 'quantity' => 1, 'total' => 320, 'variant' => 'Full'],
                ['name' => 'Butter Naan', 'quantity' => 2, 'total' => 90, 'variant' => null],
            ], $currency),
            'subtotal' => $currency . '410.00',
            'delivery_fee' => $currency . '29.00',
            'tax' => $currency . '20.50',
            'discount' => $currency . '0.00',
            'total' => $currency . '459.50',
            'app_name' => $this->appName(),
            'support_email' => $this->supportEmail(),
            'logo_url' => $this->logoUrl(),
            'track_url' => rtrim((string) config('app.url'), '/') . '/order/track',
        ];
    }

    private function variables(Order $order): array
    {
        $currency = AppSetting::sanitizedCurrencySymbol();
        $money = static fn ($value) => $currency . number_format((float) $value, AppSetting::currencyDecimals());

        $lines = is_array($order->items) ? $order->items : (json_decode((string) $order->items, true) ?: []);
        $rows = collect($lines)->map(fn ($line) => [
            'name' => $line['name'] ?? ($line['menu_item_name'] ?? 'Item'),
            'quantity' => (int) ($line['quantity'] ?? 1),
            'total' => (float) ($line['total'] ?? $line['total_price'] ?? (($line['price'] ?? 0) * ($line['quantity'] ?? 1))),
            'variant' => is_array($line['selected_variant'] ?? null) ? ($line['selected_variant']['name'] ?? null) : null,
        ])->all();

        return [
            'customer_name' => e((string) ($order->customer_name ?: ($order->customer?->name ?: 'there'))),
            'order_number' => (string) $order->order_number,
            'restaurant_name' => e((string) ($order->restaurant?->name ?? 'the restaurant')),
            'order_status' => ucwords(str_replace('_', ' ', (string) $order->status)),
            'order_date' => optional($order->created_at)->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A'),
            'payment_method' => $this->paymentMethodLabel($order),
            'delivery_address' => e((string) ($order->delivery_address ?: 'Address on file')),
            'items_table' => $this->itemsTable($rows, $currency),
            'subtotal' => $money($order->subtotal),
            'delivery_fee' => $money($order->delivery_fee),
            'tax' => $money($order->tax),
            'discount' => $money($order->discount),
            'total' => $money($order->total),
            'app_name' => $this->appName(),
            'support_email' => $this->supportEmail(),
            'logo_url' => $this->logoUrl(),
            'track_url' => rtrim((string) config('app.url'), '/') . '/order/track',
        ];
    }

    private function itemsTable(array $rows, string $currency): string
    {
        if (empty($rows)) {
            return '<tr><td style="padding:6px 0;color:#6b7280;">Your items</td></tr>';
        }

        $html = '';
        foreach ($rows as $row) {
            $name = e((string) ($row['name'] ?? 'Item'));
            $qty = (int) ($row['quantity'] ?? 1);
            $variant = ! empty($row['variant']) ? ' <span style="color:#9ca3af;">(' . e((string) $row['variant']) . ')</span>' : '';
            $amount = $currency . number_format((float) ($row['total'] ?? 0), AppSetting::currencyDecimals());
            $html .= '<tr>'
                . '<td style="padding:8px 0;border-bottom:1px solid #f1f2f4;">' . $qty . ' &times; ' . $name . $variant . '</td>'
                . '<td align="right" style="padding:8px 0;border-bottom:1px solid #f1f2f4;white-space:nowrap;">' . $amount . '</td>'
                . '</tr>';
        }

        return $html;
    }

    private function paymentMethodLabel(Order $order): string
    {
        if ($order->isCashOnDelivery()) {
            return 'Cash on Delivery';
        }

        $method = trim((string) ($order->payment_method ?: $order->payment_gateway ?: 'Online'));

        return $method === '' ? 'Online' : ucwords(str_replace('_', ' ', $method));
    }

    private function appName(): string
    {
        return (string) (AppSetting::getValue('app_name')
            ?: AppSetting::getValue('site_name')
            ?: config('app.name', 'Swado'));
    }

    private function supportEmail(): string
    {
        return (string) (AppSetting::getValue('support_email')
            ?: AppSetting::getValue('contact_email')
            ?: config('mail.from.address', 'support@example.com'));
    }

    private function logoUrl(): string
    {
        $logo = AppSetting::getValue('app_logo');

        return $logo ? (string) url(MediaStorage::url($logo)) : '';
    }

    private function fallbackSubject(): string
    {
        return 'Your {{app_name}} order {{order_number}} is confirmed';
    }

    private function interpolateFallbackSubject(array $vars): string
    {
        return strtr($this->fallbackSubject(), [
            '{{app_name}}' => $vars['app_name'] ?? 'Swado',
            '{{order_number}}' => $vars['order_number'] ?? '',
        ]);
    }

    private function fallbackBody(): string
    {
        return <<<'HTML'
<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#374151;">
  <h1 style="font-size:20px;color:#111827;margin:0 0 8px;">Order confirmed</h1>
  <p>Hi {{customer_name}}, your order <strong>{{order_number}}</strong> from {{restaurant_name}} is confirmed.</p>
  <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:16px 0;border-collapse:collapse;">{{items_table}}</table>
  <p style="font-size:14px;">Subtotal: {{subtotal}}<br>Delivery: {{delivery_fee}}<br>Tax: {{tax}}<br>Discount: -{{discount}}<br><strong>Total: {{total}}</strong></p>
  <p style="font-size:14px;"><strong>Delivering to:</strong> {{delivery_address}}</p>
  <p><a href="{{track_url}}" style="color:#111827;">Track your order</a></p>
  <p style="font-size:12px;color:#9ca3af;">Need help? {{support_email}} &middot; {{app_name}}</p>
</div>
HTML;
    }
}
