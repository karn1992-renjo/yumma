<?php

namespace App\Mail;

use App\Models\AppSetting;
use App\Models\CustomerSubscription;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/** "Welcome to Gold" email sent when a customer's Gold membership becomes active. */
class GoldMembershipWelcomeMail extends Mailable
{
    use Queueable;

    public function __construct(public CustomerSubscription $subscription)
    {
    }

    private function appName(): string
    {
        return AppSetting::getValue('app_name', config('app.name', 'Swado'));
    }

    public function envelope(): Envelope
    {
        $plan = $this->subscription->plan?->name ?: 'Gold';

        return new Envelope(subject: "Welcome to {$this->appName()} {$plan}! Your membership is active 🎉");
    }

    public function content(): Content
    {
        $subscription = $this->subscription->loadMissing(['user', 'plan']);
        $plan = $subscription->plan;
        $logo = AppSetting::getValue('app_logo');
        $payment = \App\Models\SubscriptionPayment::where('customer_subscription_id', $subscription->id)
            ->where('status', 'success')->latest('id')->first();

        $benefits = ['Free delivery on every eligible order'];
        $benefits[] = $plan && $plan->order_limit
            ? "Up to {$plan->order_limit} Gold orders each period"
            : 'Unlimited Gold orders for the whole period';
        $benefits[] = 'Exclusive member offers and early access to deals';
        $benefits[] = 'Priority support whenever you need us';
        if ($plan?->description) {
            array_unshift($benefits, trim($plan->description));
        }

        return new Content(
            view: 'emails.gold-membership-welcome',
            with: [
                'appName' => $this->appName(),
                'brand' => AppSetting::getValue('primary_color', '#F97316'),
                'logoUrl' => $logo ? url(Storage::disk('public')->url($logo)) : null,
                'supportEmail' => AppSetting::getValue('support_email', AppSetting::getValue('mail_from_address', '')),
                'supportPhone' => AppSetting::getValue('support_phone', ''),
                'firstName' => trim(explode(' ', trim((string) $subscription->user?->name))[0] ?: 'there'),
                'planName' => $plan?->name ?: 'Gold',
                'startsOn' => $subscription->current_period_start?->format('d M Y'),
                'validTill' => $subscription->current_period_end?->format('d M Y'),
                'amountPaid' => $payment ? Money::format($payment->amount) : null,
                'paymentRef' => $payment?->gateway_charge_id,
                'grantedBy' => $payment ? null : $subscription->gateway_payment_method_label,
                'benefits' => $benefits,
                'appUrl' => url('/'),
            ],
        );
    }
}
