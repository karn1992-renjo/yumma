<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VoiceAiAllowanceService
{
    public function __construct(private readonly VoiceAiSettingsService $settings)
    {
    }

    public function remainingSeconds(User $user): int
    {
        if (! $this->settings->settings()['voice_ai_allowance_enabled']) {
            return PHP_INT_MAX;
        }

        if ($user->voice_remaining_seconds === null) {
            $this->initialize($user);
            $user->refresh();
        }

        return max(0, (int) $user->voice_remaining_seconds);
    }

    public function initialize(User $user): void
    {
        $defaults = $this->settings->settings();
        $initial = (int) $defaults['voice_ai_initial_allowance_seconds'];

        User::query()
            ->whereKey($user->id)
            ->whereNull('voice_remaining_seconds')
            ->update(['voice_remaining_seconds' => $initial]);
    }

    public function consume(User $user, int $seconds): int
    {
        $seconds = max(0, $seconds);
        if ($seconds === 0) {
            return $this->remainingSeconds($user);
        }

        if (! $this->settings->settings()['voice_ai_allowance_enabled']) {
            return PHP_INT_MAX;
        }

        return DB::transaction(function () use ($user, $seconds) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->voice_remaining_seconds === null) {
                $locked->voice_remaining_seconds = (int) $this->settings->settings()['voice_ai_initial_allowance_seconds'];
            }

            $locked->voice_remaining_seconds = max(0, (int) $locked->voice_remaining_seconds - $seconds);
            $locked->save();

            return (int) $locked->voice_remaining_seconds;
        });
    }

    public function rechargeAfterOrder(Order $order): bool
    {
        $settings = $this->settings->settings();
        if (! $settings['voice_ai_allowance_enabled'] || ! $settings['voice_ai_recharge_after_order']) {
            return false;
        }

        $customerId = $order->customer_id ?? $order->user_id ?? null;
        if (! $customerId) {
            return false;
        }

        return DB::transaction(function () use ($customerId, $order, $settings) {
            $user = User::query()->whereKey($customerId)->lockForUpdate()->first();
            if (! $user || (int) $user->voice_last_recharged_order_id === (int) $order->id) {
                return false;
            }

            $seconds = (int) $settings['voice_ai_recharge_seconds'];
            if ($settings['voice_ai_recharge_mode'] === 'add') {
                $user->voice_remaining_seconds = max(0, (int) $user->voice_remaining_seconds) + $seconds;
            } else {
                $user->voice_remaining_seconds = $seconds;
            }

            $user->voice_last_recharged_order_id = $order->id;
            $user->voice_last_recharged_at = now();
            $user->save();

            return true;
        });
    }
}
