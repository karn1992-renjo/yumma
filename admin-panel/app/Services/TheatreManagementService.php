<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\Theatre;
use App\Models\TheatreAuditLog;
use App\Models\TheatreRunner;
use App\Models\TheatreUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * swaad-only: the Theatre-panel counterpart of BranchManagementService.
 * Deliberately leaner -- a theatre owner doesn't earn commission or run
 * settlements the way a branch does (they just host stalls and runners), so
 * there is no wallet/settlement/payout module here, unlike Branch.
 */
class TheatreManagementService
{
    public const OWNER_ROLE = 'theatre_owner';
    public const MANAGER_ROLE = 'theatre_manager';
    /**
     * Deliberately its own Spatie role, NOT `delivery_partner` — a runner
     * must never be swept up by AutoAssignDriverService's GPS-radius
     * auto-assignment (`User::role('delivery_partner')`) and handed a
     * normal road delivery while they're stationed inside a theatre.
     */
    public const RUNNER_ROLE = 'theatre_runner';

    public function createTheatre(array $data, ?User $actor = null): Theatre
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->ensureTheatreRoles();

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

            $theatre = Theatre::create(array_merge($data, ['owner_user_id' => $owner->id]));
            $owner->forceFill(['theatre_id' => $theatre->id])->save();

            TheatreUser::updateOrCreate(
                ['theatre_id' => $theatre->id, 'user_id' => $owner->id],
                ['role' => self::OWNER_ROLE, 'permissions' => $this->permissionsFor(self::OWNER_ROLE), 'is_active' => true]
            );

            $this->audit($theatre, $actor, 'theatre.created', $theatre, null, $theatre->toArray());

            return $theatre;
        });
    }

    /**
     * @return array<string, string> permission key => human label
     */
    public function permissionCatalog(): array
    {
        return [
            'theatre.dashboard.view' => 'View Dashboard',
            'theatre.restaurants.view' => 'View Mapped Restaurants',
            'theatre.restaurants.manage' => 'Map / Unmap Restaurants',
            'theatre.screens.view' => 'View Screens & Seat Maps',
            'theatre.screens.manage' => 'Create / Edit Screens & Seat Maps',
            'theatre.runners.view' => 'View Runner Roster',
            'theatre.runners.manage' => 'Add / Remove Runners',
            'theatre.orders.view' => 'View Orders',
        ];
    }

    public function permissionsFor(string $role): array
    {
        return match ($role) {
            self::OWNER_ROLE => array_keys($this->permissionCatalog()),
            self::MANAGER_ROLE => [
                'theatre.dashboard.view',
                'theatre.restaurants.view',
                'theatre.screens.view',
                'theatre.runners.view',
                'theatre.runners.manage',
                'theatre.orders.view',
            ],
            default => ['theatre.dashboard.view'],
        };
    }

    /**
     * Restore a theatre member's permissions to the current role default --
     * mirrors BranchManagementService::resyncPermissions(), the same fix for
     * the same class of bug (a stored `permissions` predating a newly added
     * feature, or the row missing entirely).
     */
    public function resyncPermissions(Theatre $theatre, User $user, string $role): TheatreUser
    {
        return TheatreUser::updateOrCreate(
            ['theatre_id' => $theatre->id, 'user_id' => $user->id],
            ['role' => $role, 'permissions' => $this->permissionsFor($role), 'is_active' => true]
        );
    }

    public function ensureTheatreRoles(): void
    {
        foreach ([self::OWNER_ROLE, self::MANAGER_ROLE, self::RUNNER_ROLE] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function theatreForUser(User $user): ?Theatre
    {
        if ($user->theatre_id) {
            return Theatre::find($user->theatre_id);
        }

        return $user->theatreMembership?->theatre;
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        $theatre = $this->theatreForUser($user);

        return $theatre ? $query->where('theatre_id', $theatre->id) : $query->whereRaw('1 = 0');
    }

    public function mapRestaurant(Restaurant $restaurant, Theatre $theatre, ?User $actor = null): void
    {
        DB::transaction(function () use ($restaurant, $theatre, $actor) {
            $theatre->restaurants()->syncWithoutDetaching([
                $restaurant->id => ['is_active' => true, 'added_by' => $actor?->id],
            ]);

            $this->audit($theatre, $actor, 'restaurant.mapped', $restaurant, null, ['theatre_id' => $theatre->id]);
        });
    }

    /**
     * Attaches a restaurant the theatre owner JUST CREATED (as opposed to
     * mapRestaurant() above, which links an existing, already-verified one)
     * -- mirrors branch_restaurants' approval_status/approved_by/approved_at
     * exactly (BranchManagementService::assignRestaurant()). A newly-created
     * restaurant is also saved with Restaurant.is_verified = false by the
     * caller, so it surfaces on the EXISTING admin "Pending" restaurant
     * filter/edit-to-verify flow -- no new admin-side approval UI needed,
     * same mechanism every other restaurant on the platform already goes
     * through.
     */
    public function assignOwnRestaurant(Restaurant $restaurant, Theatre $theatre, ?User $actor = null, string $status = 'theatre_pending'): void
    {
        DB::transaction(function () use ($restaurant, $theatre, $actor, $status) {
            $theatre->restaurants()->syncWithoutDetaching([
                $restaurant->id => [
                    'is_active' => true,
                    'added_by' => $actor?->id,
                    'approval_status' => $status,
                    'approved_by' => $status === 'approved' ? $actor?->id : null,
                    'approved_at' => $status === 'approved' ? now() : null,
                ],
            ]);

            $this->audit($theatre, $actor, 'restaurant.created', $restaurant, null, ['theatre_id' => $theatre->id]);
        });
    }

    public function unmapRestaurant(Restaurant $restaurant, Theatre $theatre, ?User $actor = null): void
    {
        DB::transaction(function () use ($restaurant, $theatre, $actor) {
            $theatre->restaurants()->detach($restaurant->id);

            $this->audit($theatre, $actor, 'restaurant.unmapped', $restaurant, ['theatre_id' => $theatre->id], null);
        });
    }

    public function addRunner(User $runner, Theatre $theatre, ?User $actor = null, string $status = 'active'): TheatreRunner
    {
        return DB::transaction(function () use ($runner, $theatre, $actor, $status) {
            $this->ensureTheatreRoles();
            if (! $runner->hasRole(self::RUNNER_ROLE)) {
                $runner->assignRole(self::RUNNER_ROLE);
            }
            $runner->forceFill(['theatre_id' => $theatre->id])->save();

            $roster = TheatreRunner::updateOrCreate(
                ['theatre_id' => $theatre->id, 'runner_id' => $runner->id],
                ['approval_status' => $status]
            );

            $this->audit($theatre, $actor, 'runner.added', $runner, null, ['theatre_id' => $theatre->id]);

            return $roster;
        });
    }

    public function removeRunner(User $runner, Theatre $theatre, ?User $actor = null): void
    {
        DB::transaction(function () use ($runner, $theatre, $actor) {
            TheatreRunner::where('theatre_id', $theatre->id)->where('runner_id', $runner->id)->delete();

            if ((int) $runner->theatre_id === (int) $theatre->id) {
                $runner->forceFill(['theatre_id' => null])->save();
            }

            $this->audit($theatre, $actor, 'runner.removed', $runner, ['theatre_id' => $theatre->id], null);
        });
    }

    /**
     * Pushes "an order is ready to claim" to every active runner at the
     * order's theatre -- there is no single assigned driver to notify yet
     * (that's the whole point: runners browse a shared queue and claim,
     * see Api\TheatreRunnerController::queue()), so this fans out to the
     * whole roster instead of one recipient, same shape as
     * OrderReleaseService::notifyRestaurant()'s owner+staff fan-out.
     */
    public function notifyRunnersOrderReady(\App\Models\Order $order): void
    {
        if (! $order->theatre_id) {
            return;
        }

        // Same source of truth Api\TheatreRunnerController::queue()/claim()
        // authorize against (users.theatre_id + the theatre_runner role) --
        // not the theatre_runners roster table, which addRunner()/
        // removeRunner() keep in sync but which a claim itself never
        // actually checks. Notifying by the roster instead could silently
        // miss a runner who can genuinely claim, or notify one who can't.
        $runners = User::where('theatre_id', $order->theatre_id)
            ->role(self::RUNNER_ROLE)
            ->get(['id', 'fcm_token', 'driver_fcm_token']);
        if ($runners->isEmpty()) {
            return;
        }

        $title = 'Order ready to claim';
        $body = "Order #{$order->order_number} is ready for pickup at your theatre.";
        $payload = [
            'type' => 'THEATRE_ORDER_READY',
            'event' => 'theatre_order_ready',
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'theatre_id' => (string) $order->theatre_id,
            'seat_label' => (string) ($order->seat_label ?? ''),
            'android_channel_id' => 'incoming_order_channel',
            'priority' => 'high',
        ];

        try {
            foreach ($runners as $runner) {
                $runner->notify(new \App\Notifications\AppDatabaseNotification($title, $body, $payload));
            }

            app(\App\Helpers\FirebaseHelper::class)->sendToDevices(
                $runners
                    ->map(fn (User $runner) => $runner->fcmTokenForApp('driver'))
                    ->filter(fn ($token) => filled($token))
                    ->unique()
                    ->values()
                    ->all(),
                $title,
                $body,
                $payload
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Theatre runner order-ready push failed.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function audit(?Theatre $theatre, ?User $actor, string $action, object $entity, ?array $oldValues, ?array $newValues): void
    {
        TheatreAuditLog::create([
            'theatre_id' => $theatre?->id,
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
