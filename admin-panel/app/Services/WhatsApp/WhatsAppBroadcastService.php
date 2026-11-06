<?php

namespace App\Services\WhatsApp;

use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\Log;

/**
 * Template campaigns to a chosen audience. Recipients are stored first, then sent in small
 * batches (a scheduled command), so a long campaign survives restarts and never blocks a request.
 * Opted-out customers and numbers that are not valid are always skipped.
 */
class WhatsAppBroadcastService
{
    public const AUDIENCES = [
        'all_users' => 'Everyone: all users with a phone number (narrow with Roles)',
        'contacts' => 'Everyone who has chatted with us on WhatsApp',
        'recent_customers' => 'Customers who ordered in the last N days',
        'gold' => 'Active Gold members',
        'role_customers' => 'Role: all customers',
        'role_restaurants' => 'Role: restaurant owners and staff',
        'role_drivers' => 'Role: delivery partners (drivers)',
        'role_branches' => 'Role: branch owners, managers and staff',
        'role_franchise' => 'Role: franchise partners',
        'role_staff' => 'Role: admin team (admins, support, marketing, fleet, accounts)',
    ];

    /** Audience key => roles whose users receive it. Franchise partners are handled separately. */
    private const ROLE_AUDIENCES = [
        'role_customers' => ['customer'],
        'role_restaurants' => ['restaurant_owner', 'restaurant_staff'],
        'role_drivers' => ['delivery_partner'],
        'role_branches' => ['branch_owner', 'branch_manager', 'branch_staff'],
        'role_staff' => ['super_admin', 'admin', 'support', 'marketing_manager', 'fleet_manager', 'accountant', 'hr_manager'],
    ];

    /** Roles an audience can be limited to: key => label. */
    public const ROLES = [
        'customer' => 'Customers',
        'restaurant_owner' => 'Restaurant owners',
        'restaurant_staff' => 'Restaurant staff',
        'delivery_partner' => 'Delivery partners (drivers)',
        'branch_owner' => 'Branch owners',
        'branch_manager' => 'Branch managers',
        'branch_staff' => 'Branch staff',
        'admin' => 'Admins',
        'support' => 'Support',
        'marketing_manager' => 'Marketing managers',
        'fleet_manager' => 'Fleet managers',
        'accountant' => 'Accountants',
    ];

    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /** Builds the recipient list and marks the broadcast as sending. @return int recipients */
    public function start(WhatsAppBroadcast $broadcast): int
    {
        $rows = $this->audience($broadcast);

        foreach ($rows as $phone => $firstName) {
            WhatsAppBroadcastRecipient::firstOrCreate(
                ['broadcast_id' => $broadcast->id, 'phone' => $phone],
                ['first_name' => $firstName, 'status' => 'pending']
            );
        }

        $broadcast->update([
            'status' => 'sending', 'total' => $broadcast->recipients()->count(), 'started_at' => now(),
        ]);

        return $broadcast->total;
    }

    /** @return array<string, ?string> phone => first name */
    private function audience(WhatsAppBroadcast $broadcast): array
    {
        $out = [];
        // "Limit to roles": when roles are ticked, only people with one of those roles receive it, whatever the audience.
        $roles = array_values(array_filter((array) ($broadcast->roles ?? [])));
        $add = function (?string $phone, ?string $name, ?User $user = null) use (&$out, $roles) {
            if (! $phone) {
                return;
            }
            if ($roles && (! $user || ! $user->hasAnyRole($roles))) {
                return;
            }
            try {
                $out[$this->whatsapp->normalize($phone)] ??= $name ? trim(explode(' ', $name)[0]) : null;
            } catch (\Throwable) {
            }
        };

        match ($broadcast->audience) {
            'all_users' => User::whereNotNull('phone')->where('phone', '!=', '')->each(fn ($u) => $add($u->phone, $u->name, $u)),
            'contacts' => WhatsAppContact::where('opted_in', true)->each(fn ($c) => $add($c->phone, $c->profile_name ?: $c->user?->name, $c->user)),
            'recent_customers' => User::whereIn('id', Order::where('created_at', '>=', now()->subDays(max(1, (int) $broadcast->audience_days ?: 30)))->pluck('customer_id'))
                ->whereNotNull('phone')->each(fn ($u) => $add($u->phone, $u->name, $u)),
            'gold' => User::whereIn('id', \App\Models\CustomerSubscription::live()->pluck('user_id'))
                ->whereNotNull('phone')->each(fn ($u) => $add($u->phone, $u->name, $u)),
            'role_franchise' => ! class_exists(\App\Models\Franchise::class) ? null : User::whereIn('id', \App\Models\Franchise::whereNotNull('owner_user_id')->pluck('owner_user_id'))
                ->whereNotNull('phone')->each(fn ($u) => $add($u->phone, $u->name, $u)),
            default => null,
        };

        if ($roles = self::ROLE_AUDIENCES[$broadcast->audience] ?? null) {
            $existing = \Spatie\Permission\Models\Role::whereIn('name', $roles)->where('guard_name', 'web')->pluck('name')->all();
            if ($existing) {
                User::role($existing)->whereNotNull('phone')->where('phone', '!=', '')->each(fn ($u) => $add($u->phone, $u->name, $u));
            }
        }

        return $out;
    }

    /** Sends the next batch of every running broadcast. @return int messages attempted */
    public function runBatch(int $limit = 100): int
    {
        if (! $this->whatsapp->enabled()) {
            return 0;
        }

        $attempted = 0;
        foreach (WhatsAppBroadcast::where('status', 'sending')->get() as $broadcast) {
            $batch = $broadcast->recipients()->where('status', 'pending')->limit(max(1, $limit - $attempted))->get();

            foreach ($batch as $recipient) {
                $params = collect($broadcast->params ?? [])
                    ->map(fn ($p) => WhatsAppBroadcastVariables::fill((string) $p, $recipient->phone, $recipient->first_name))->all();
                try {
                    $message = $this->whatsapp->sendRawTemplate(
                        $recipient->phone, $broadcast->template, $params,
                        (string) \App\Models\AppSetting::getValue('whatsapp_template_language', 'en'),
                        $broadcast->header_image ?: null
                    );
                    $message->forceFill(['event' => 'broadcast', 'related_type' => $broadcast->getMorphClass(), 'related_id' => $broadcast->id])->save();
                    $recipient->update(['status' => $message->status === 'failed' ? 'failed' : 'sent', 'error' => $message->error]);
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp broadcast send failed.', ['broadcast' => $broadcast->id, 'error' => $e->getMessage()]);
                    $recipient->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300)]);
                }
                $attempted++;
            }

            $this->refresh($broadcast);
            if ($attempted >= $limit) {
                break;
            }
        }

        return $attempted;
    }

    public function refresh(WhatsAppBroadcast $broadcast): void
    {
        $counts = $broadcast->recipients()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $pending = (int) ($counts['pending'] ?? 0);
        $broadcast->update([
            'sent' => (int) ($counts['sent'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0),
            'status' => $pending === 0 && $broadcast->status === 'sending' ? 'done' : $broadcast->status,
            'finished_at' => $pending === 0 && $broadcast->status === 'sending' ? now() : $broadcast->finished_at,
        ]);
    }

    public function cancel(WhatsAppBroadcast $broadcast): void
    {
        $broadcast->recipients()->where('status', 'pending')->update(['status' => 'skipped']);
        $broadcast->update(['status' => 'cancelled', 'finished_at' => now()]);
        $this->refresh($broadcast->fresh());
    }
}
