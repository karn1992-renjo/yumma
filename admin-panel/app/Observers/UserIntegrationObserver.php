<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Integration\WebhookDispatcher;

/**
 * Streams identity changes for the standalone-app roles out to Accounts/ and
 * HRMS/ so their local `users` tables (and Spatie roles) stay in step. A no-op
 * unless the matching integration toggle is on.
 */
class UserIntegrationObserver
{
    public function saved(User $user): void
    {
        $this->sync($user);
    }

    public function deleted(User $user): void
    {
        $roles = $user->getRoleNames()->all();
        if (array_intersect($roles, ['accountant'])) {
            WebhookDispatcher::emit('accounts', 'user.deleted', ['email' => $user->email]);
        }
        if (array_intersect($roles, ['hr_manager', 'employee'])) {
            WebhookDispatcher::emit('hrms', 'user.deleted', ['email' => $user->email]);
        }
    }

    public function sync(User $user): void
    {
        $roles = $user->getRoleNames()->all();
        $payload = [
            'source_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => $user->getAuthPassword(), // already-hashed
            'is_active' => (bool) ($user->is_active ?? true),
            'roles' => $roles,
        ];

        if (in_array('accountant', $roles, true)) {
            WebhookDispatcher::emit('accounts', 'user.upserted', $payload);
        }
        if (array_intersect($roles, ['hr_manager', 'employee'])) {
            WebhookDispatcher::emit('hrms', 'user.upserted', $payload);
        }
    }
}
