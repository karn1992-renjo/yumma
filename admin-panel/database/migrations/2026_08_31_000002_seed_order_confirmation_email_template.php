<?php

use App\Models\NotificationTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const KEY = 'email.order_confirmation';

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        NotificationTemplate::updateOrCreate(
            ['key' => self::KEY],
            [
                'channel' => 'email',
                'label' => 'Order Confirmation (Email)',
                'group' => 'email',
                'title' => 'Your {{app_name}} order {{order_number}} is confirmed',
                'body' => $this->defaultBody(),
                'placeholders' => [
                    'customer_name', 'order_number', 'restaurant_name', 'order_status',
                    'order_date', 'payment_method', 'delivery_address', 'items_table',
                    'subtotal', 'delivery_fee', 'tax', 'discount', 'total',
                    'app_name', 'support_email', 'logo_url', 'track_url',
                ],
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        NotificationTemplate::where('key', self::KEY)->delete();
    }

    private function defaultBody(): string
    {
        return <<<'HTML'
<div style="margin:0;padding:0;background:#f4f5f7;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
          <tr>
            <td style="padding:28px 32px 8px;">
              <img src="{{logo_url}}" alt="{{app_name}}" height="34" style="height:34px;display:block;border:0;">
            </td>
          </tr>
          <tr>
            <td style="padding:8px 32px 0;">
              <h1 style="margin:0;font-size:22px;color:#111827;">Order confirmed 🎉</h1>
              <p style="margin:8px 0 0;font-size:15px;color:#4b5563;line-height:1.6;">
                Hi {{customer_name}}, thanks for your order. {{restaurant_name}} has received it and will start preparing soon.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 32px 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;">
                <tr>
                  <td style="padding:16px 18px;font-size:13px;color:#6b7280;">
                    <strong style="color:#111827;font-size:15px;">Order {{order_number}}</strong><br>
                    {{order_date}} &middot; {{payment_method}} &middot; {{order_status}}
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:22px 32px 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#374151;border-collapse:collapse;">
                {{items_table}}
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:12px 32px 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#374151;border-top:1px solid #e5e7eb;">
                <tr><td style="padding:10px 0 2px;">Subtotal</td><td align="right" style="padding:10px 0 2px;">{{subtotal}}</td></tr>
                <tr><td style="padding:2px 0;">Delivery fee</td><td align="right" style="padding:2px 0;">{{delivery_fee}}</td></tr>
                <tr><td style="padding:2px 0;">Taxes</td><td align="right" style="padding:2px 0;">{{tax}}</td></tr>
                <tr><td style="padding:2px 0;">Discount</td><td align="right" style="padding:2px 0;">-{{discount}}</td></tr>
                <tr><td style="padding:10px 0;border-top:1px solid #e5e7eb;font-weight:bold;color:#111827;">Total</td><td align="right" style="padding:10px 0;border-top:1px solid #e5e7eb;font-weight:bold;color:#111827;">{{total}}</td></tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 32px 0;font-size:14px;color:#374151;">
              <strong style="color:#111827;">Delivering to</strong><br>{{delivery_address}}
            </td>
          </tr>
          <tr>
            <td style="padding:24px 32px 8px;">
              <a href="{{track_url}}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:12px 22px;border-radius:9px;">Track your order</a>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 30px;font-size:12px;color:#9ca3af;line-height:1.7;">
              Need help? Contact us at <a href="mailto:{{support_email}}" style="color:#6b7280;">{{support_email}}</a>.<br>
              &copy; {{app_name}}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</div>
HTML;
    }
};
