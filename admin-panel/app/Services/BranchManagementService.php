<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchAuditLog;
use App\Models\BranchPayout;
use App\Models\BranchSettlement;
use App\Models\BranchTransferHistory;
use App\Models\BranchUser;
use App\Models\BranchWallet;
use App\Models\BranchWalletTransaction;
use App\Models\BranchZone;
use App\Models\CommissionSetting;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BranchManagementService
{
    public const OWNER_ROLE = 'branch_owner';
    public const MANAGER_ROLE = 'branch_manager';
    public const STAFF_ROLE = 'branch_staff';

    public function createBranch(array $data, ?User $actor = null): Branch
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->ensureBranchRoles();

            $owner = User::firstOrCreate(
                ['email' => $data['owner_email']],
                [
                    'name' => $data['owner_name'],
                    'phone' => $data['owner_phone'],
                    'password' => Hash::make($data['owner_password'] ?? Str::random(16)),
                    'is_active' => true,
                ]
            );
            $owner->assignRole(self::OWNER_ROLE);

            $branch = Branch::create(array_merge($data, ['owner_user_id' => $owner->id]));
            $owner->forceFill(['branch_id' => $branch->id])->save();

            BranchUser::updateOrCreate(
                ['branch_id' => $branch->id, 'user_id' => $owner->id],
                ['role' => self::OWNER_ROLE, 'permissions' => $this->permissionsFor(self::OWNER_ROLE), 'is_active' => true]
            );

            BranchWallet::firstOrCreate(['branch_id' => $branch->id]);

            $this->audit($branch, $actor, 'branch.created', $branch, null, $branch->toArray());

            return $branch;
        });
    }

    public function ensureBranchRoles(): void
    {
        foreach ([self::OWNER_ROLE, self::MANAGER_ROLE, self::STAFF_ROLE] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function permissionsFor(string $role): array
    {
        return match ($role) {
            self::OWNER_ROLE => [
                'manage_restaurants',
                'manage_drivers',
                'manage_zones',
                'manage_staff',
                'view_orders',
                'view_earnings',
                'view_wallet',
                'create_promotions',
                'view_reports',
                'submit_settlement_requests',
            ],
            self::MANAGER_ROLE => [
                'manage_orders',
                'manage_drivers',
                'manage_restaurants',
                'manage_zones',
                'view_reports',
            ],
            default => [
                'view_assigned_tasks',
                'manage_support_tickets',
            ],
        };
    }

    public function branchForUser(User $user): ?Branch
    {
        if ($user->branch_id) {
            return Branch::find($user->branch_id);
        }

        return $user->branchMembership?->branch;
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        $branch = $this->branchForUser($user);

        return $branch ? $query->where('branch_id', $branch->id) : $query->whereRaw('1 = 0');
    }

    public function assignRestaurant(Restaurant $restaurant, Branch $branch, ?User $actor = null, string $status = 'branch_pending'): void
    {
        DB::transaction(function () use ($restaurant, $branch, $actor, $status) {
            $old = ['branch_id' => $restaurant->branch_id];
            $restaurant->forceFill(['branch_id' => $branch->id])->save();

            DB::table('branch_restaurants')->updateOrInsert(
                ['restaurant_id' => $restaurant->id],
                [
                    'branch_id' => $branch->id,
                    'approval_status' => $status,
                    'approved_by' => in_array($status, ['approved', 'active'], true) ? $actor?->id : null,
                    'approved_at' => in_array($status, ['approved', 'active'], true) ? now() : null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $this->audit($branch, $actor, 'restaurant.assigned', $restaurant, $old, ['branch_id' => $branch->id]);
        });
    }

    public function assignDriver(User $driver, Branch $branch, ?User $actor = null, string $status = 'active'): void
    {
        DB::transaction(function () use ($driver, $branch, $actor, $status) {
            $old = ['branch_id' => $driver->branch_id];
            $driver->forceFill(['branch_id' => $branch->id])->save();

            DB::table('branch_drivers')->updateOrInsert(
                ['driver_id' => $driver->id],
                [
                    'branch_id' => $branch->id,
                    'approval_status' => $status,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $this->audit($branch, $actor, 'driver.assigned', $driver, $old, ['branch_id' => $branch->id]);
        });
    }

    public function resolveBranchForRestaurant(Restaurant $restaurant): ?Branch
    {
        if ($restaurant->branch_id) {
            return Branch::find($restaurant->branch_id);
        }

        if ($restaurant->latitude !== null && $restaurant->longitude !== null) {
            $deliveryAreaZone = BranchZone::query()
                ->with(['branch', 'deliveryArea'])
                ->where('is_active', true)
                ->whereNotNull('delivery_area_id')
                ->get()
                ->first(fn (BranchZone $zone) => $zone->deliveryArea?->containsPoint((float) $restaurant->latitude, (float) $restaurant->longitude));

            if ($deliveryAreaZone?->branch) {
                return $deliveryAreaZone->branch;
            }
        }

        $zone = BranchZone::query()
            ->where('is_active', true)
            ->whereNull('delivery_area_id')
            ->where(function ($query) use ($restaurant) {
                $query->whereNull('city')->orWhereRaw('LOWER(city) = ?', [Str::lower((string) $restaurant->city)]);
            })
            ->where(function ($query) use ($restaurant) {
                $query->whereNull('area')->orWhereRaw('LOWER(area) = ?', [Str::lower((string) $restaurant->address)]);
            })
            ->where(function ($query) use ($restaurant) {
                $query->whereNull('pincode')->orWhere('pincode', $restaurant->pincode);
            })
            ->first();

        return $zone?->branch;
    }

    public function stampOrder(Order $order): void
    {
        if ($order->branch_id || ! $order->restaurant) {
            return;
        }

        $branch = $this->resolveBranchForRestaurant($order->restaurant);
        if (! $branch) {
            return;
        }

        $order->branch_id = $branch->id;
        $this->calculateOrderCommission($order, $branch);
        $order->save();
    }

    public function calculateOrderCommission(Order $order, Branch $branch): void
    {
        $base = max(0, (float) $order->subtotal);
        $restaurantRate = $order->restaurant?->commission_rate;
        $restaurantType = $order->restaurant?->commission_calculation_type;
        if ($restaurantType !== 'global' && $restaurantRate !== null && $restaurantRate !== '') {
            $platform = $order->restaurant?->commission_calculation_type === CommissionSetting::TYPE_FIXED
                ? min($base, (float) $restaurantRate)
                : $base * ((float) $restaurantRate / 100);
        } else {
            $platform = CommissionSetting::calculate('restaurant', $base, 15);
        }
        $platform = round(max(0, $platform), 2);
        $branchCommission = round($platform * ((float) $branch->branch_share_percent / 100), 2);
        $adminCommission = round($platform - $branchCommission, 2);

        $order->platform_commission = $platform;
        $order->branch_commission = $branchCommission;
        $order->admin_commission = $adminCommission;
    }

    public function creditCompletedOrder(Order $order): void
    {
        if (! $order->branch_id || $order->branch_commission_settled || (float) $order->branch_commission <= 0) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            if (! $lockedOrder->branch_id
                || $lockedOrder->branch_commission_settled
                || (float) $lockedOrder->branch_commission <= 0) {
                return;
            }

            $wallet = BranchWallet::where('branch_id', $lockedOrder->branch_id)->lockForUpdate()->first()
                ?? BranchWallet::create(['branch_id' => $lockedOrder->branch_id]);

            $wallet->balance = (float) $wallet->balance + (float) $lockedOrder->branch_commission;
            $wallet->lifetime_earnings = (float) $wallet->lifetime_earnings + (float) $lockedOrder->branch_commission;
            $wallet->save();

            BranchWalletTransaction::create([
                'branch_wallet_id' => $wallet->id,
                'branch_id' => $lockedOrder->branch_id,
                'order_id' => $lockedOrder->id,
                'type' => 'commission_earning',
                'amount' => $lockedOrder->branch_commission,
                'balance_after' => $wallet->balance,
                'description' => 'Commission locked for order ' . $lockedOrder->order_number,
            ]);

            $lockedOrder->forceFill(['branch_commission_settled' => true])->save();
        });
    }

    public function reverseRefund(Order $order, ?float $amount = null): void
    {
        if (! $order->branch_id || ! $order->branch_commission_settled) {
            return;
        }

        $refundRatio = $amount && (float) $order->total > 0 ? min(1, $amount / (float) $order->total) : 1;
        $branchDeduction = round((float) $order->branch_commission * $refundRatio, 2);

        if ($branchDeduction <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $branchDeduction, $refundRatio) {
            $wallet = BranchWallet::where('branch_id', $order->branch_id)->lockForUpdate()->first();
            if (! $wallet) {
                return;
            }

            $alreadyReversed = BranchWalletTransaction::where('branch_id', $order->branch_id)
                ->where('order_id', $order->id)
                ->where('type', 'refund_deduction')
                ->exists();
            if ($alreadyReversed) {
                return;
            }

            $wallet->balance = (float) $wallet->balance - $branchDeduction;
            $wallet->save();

            BranchWalletTransaction::create([
                'branch_wallet_id' => $wallet->id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'type' => 'refund_deduction',
                'amount' => -1 * $branchDeduction,
                'balance_after' => $wallet->balance,
                'description' => 'Refund commission reversal for order ' . $order->order_number,
                'meta' => ['refund_ratio' => $refundRatio],
            ]);
        });
    }

    public function generateSettlement(Branch $branch, string $startDate, string $endDate, ?User $actor = null): BranchSettlement
    {
        return DB::transaction(function () use ($branch, $startDate, $endDate, $actor) {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $overlapsExistingSettlement = BranchSettlement::where('branch_id', $branch->id)
                ->whereIn('status', ['pending', 'approved', 'closed'])
                ->whereDate('period_start', '<=', $endDate)
                ->whereDate('period_end', '>=', $startDate)
                ->exists();
            if ($overlapsExistingSettlement) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'period_start' => 'This period overlaps an existing branch settlement.',
                ]);
            }

            $orders = $branch->orders()
                ->where('status', 'delivered')
                ->where('branch_commission_settled', true)
                ->whereBetween('delivered_at', [
                    \Carbon\Carbon::parse($startDate)->startOfDay(),
                    \Carbon\Carbon::parse($endDate)->endOfDay(),
                ])
                ->get();

            if ($orders->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'period_start' => 'No payable delivered orders exist in this period.',
                ]);
            }

            $settlement = BranchSettlement::create([
                'branch_id' => $branch->id,
                'settlement_number' => 'BST' . now()->format('YmdHis') . random_int(100, 999),
                'period_start' => $startDate,
                'period_end' => $endDate,
                'gross_orders' => $orders->sum('total'),
                'platform_commission' => $orders->sum('platform_commission'),
                'branch_commission' => $orders->sum('branch_commission'),
                'admin_commission' => $orders->sum('admin_commission'),
                'amount' => $orders->sum('branch_commission'),
                'status' => 'pending',
                'requested_by' => $actor?->id,
            ]);

            $this->audit($branch, $actor, 'settlement.generated', $settlement, null, $settlement->toArray());

            return $settlement;
        });
    }

    public function approveSettlement(BranchSettlement $settlement, User $actor): BranchPayout
    {
        return DB::transaction(function () use ($settlement, $actor) {
            $settlement = BranchSettlement::lockForUpdate()->findOrFail($settlement->id);
            if ($settlement->status !== 'pending') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'settlement' => 'Only pending settlements can be approved.',
                ]);
            }

            $settlement->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $payout = BranchPayout::create([
                'branch_id' => $settlement->branch_id,
                'branch_settlement_id' => $settlement->id,
                'amount' => $settlement->amount,
                'period_start' => $settlement->period_start,
                'period_end' => $settlement->period_end,
                'status' => 'approved',
                'approved_by' => $actor->id,
            ]);

            $this->audit($settlement->branch, $actor, 'settlement.approved', $settlement, null, $settlement->fresh()->toArray());

            return $payout;
        });
    }

    public function requestWithdrawal(Branch $branch, float $amount, ?User $actor = null, ?string $notes = null): BranchPayout
    {
        return DB::transaction(function () use ($branch, $amount, $actor, $notes) {
            $wallet = BranchWallet::where('branch_id', $branch->id)->lockForUpdate()->first()
                ?? BranchWallet::create(['branch_id' => $branch->id]);

            if ($amount <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Withdrawal amount must be greater than zero.',
                ]);
            }

            if ((float) $wallet->balance < $amount) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Insufficient branch wallet balance for this withdrawal request.',
                ]);
            }

            $wallet->balance = (float) $wallet->balance - $amount;
            $wallet->locked_balance = (float) $wallet->locked_balance + $amount;
            $wallet->save();

            $payout = BranchPayout::create([
                'branch_id' => $branch->id,
                'branch_settlement_id' => null,
                'amount' => $amount,
                'period_start' => now()->toDateString(),
                'period_end' => now()->toDateString(),
                'status' => 'pending',
                'notes' => $notes ?: 'Manual withdrawal requested by branch.',
            ]);

            BranchWalletTransaction::create([
                'branch_wallet_id' => $wallet->id,
                'branch_id' => $branch->id,
                'settlement_id' => null,
                'type' => 'withdrawal_hold',
                'amount' => -1 * $amount,
                'balance_after' => $wallet->balance,
                'description' => 'Withdrawal request submitted',
                'meta' => [
                    'branch_payout_id' => $payout->id,
                    'requested_by' => $actor?->id,
                ],
            ]);

            $this->audit($branch, $actor, 'withdrawal.requested', $payout, null, $payout->toArray());

            return $payout;
        });
    }

    public function markPayoutPaid(BranchPayout $payout, User $actor, ?string $reference = null): void
    {
        DB::transaction(function () use ($payout, $actor, $reference) {
            $payout = BranchPayout::lockForUpdate()->findOrFail($payout->id);
            if ($payout->status === 'paid') {
                return;
            }

            if (! in_array($payout->status, ['pending', 'approved'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payout' => 'This branch payout cannot be marked as paid.',
                ]);
            }

            $wallet = BranchWallet::where('branch_id', $payout->branch_id)->lockForUpdate()->first();
            if (! $wallet) {
                return;
            }

            if ($payout->branch_settlement_id) {
                if ((float) $wallet->balance < (float) $payout->amount) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'payout' => 'Insufficient branch wallet balance for this payout.',
                    ]);
                }
                $wallet->balance = (float) $wallet->balance - (float) $payout->amount;
            } else {
                $wallet->locked_balance = max(0, (float) $wallet->locked_balance - (float) $payout->amount);
            }

            $wallet->lifetime_settled = (float) $wallet->lifetime_settled + (float) $payout->amount;
            $wallet->save();

            BranchWalletTransaction::create([
                'branch_wallet_id' => $wallet->id,
                'branch_id' => $payout->branch_id,
                'settlement_id' => $payout->branch_settlement_id,
                'type' => $payout->branch_settlement_id ? 'settlement_debit' : 'withdrawal_paid',
                'amount' => -1 * (float) $payout->amount,
                'balance_after' => $wallet->balance,
                'description' => $payout->branch_settlement_id ? 'Branch payout paid' : 'Branch withdrawal paid',
                'meta' => ['branch_payout_id' => $payout->id],
            ]);

            $payout->update([
                'status' => 'paid',
                'transaction_reference' => $reference,
                'paid_at' => now(),
            ]);

            $payout->settlement?->update(['status' => 'closed']);
            $this->audit($payout->branch, $actor, 'payout.paid', $payout, null, $payout->fresh()->toArray());
        });
    }

    public function transferEntity(string $type, int $id, Branch $toBranch, ?User $actor = null, ?string $reason = null): void
    {
        $model = match ($type) {
            'restaurant' => Restaurant::findOrFail($id),
            'driver' => User::role('delivery_partner')->findOrFail($id),
            'zone' => BranchZone::findOrFail($id),
            default => throw new \InvalidArgumentException('Unsupported branch transfer type.'),
        };

        DB::transaction(function () use ($model, $toBranch, $actor, $reason, $type) {
            $fromBranchId = $model->branch_id;
            $model->forceFill(['branch_id' => $toBranch->id])->save();

            BranchTransferHistory::create([
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranch->id,
                'transferable_type' => $model::class,
                'transferable_id' => $model->id,
                'transferred_by' => $actor?->id,
                'reason' => $reason,
            ]);

            $this->audit($toBranch, $actor, $type . '.transferred', $model, ['branch_id' => $fromBranchId], ['branch_id' => $toBranch->id]);
        });
    }

    public function canDeactivate(Branch $branch): array
    {
        return [
            'active_orders' => $branch->orders()->whereNotIn('status', ['delivered', 'cancelled', 'refunded'])->count(),
            'pending_settlements' => $branch->settlements()->whereIn('status', ['pending', 'approved'])->count(),
            'pending_refunds' => $branch->orders()->whereIn('refund_status', ['pending', 'processing'])->count(),
            'open_tickets' => $branch->hasMany(\App\Models\BranchTicket::class)->whereIn('status', ['open', 'pending'])->count(),
        ];
    }

    public function audit(?Branch $branch, ?User $actor, string $action, object $entity, ?array $oldValues, ?array $newValues): void
    {
        BranchAuditLog::create([
            'branch_id' => $branch?->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->id ?? null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
        ]);
    }
}
