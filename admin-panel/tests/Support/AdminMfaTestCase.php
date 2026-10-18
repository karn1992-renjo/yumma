<?php

namespace Tests\Support;

use App\Http\Middleware\EnsureInstallationCompleted;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class AdminMfaTestCase extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        // Never use deployed database, cache, session or integration credentials.
        $app->loadEnvironmentFrom('.env.mfa-tests');
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
                'database.default' => 'sqlite',
                'database.connections.sqlite.url' => null,
                'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array',
                'session.driver' => 'array',
                'broadcasting.default' => 'null',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_04_25_111115_create_permission_tables.php',
            '2026_04_25_143214_add_two_factor_columns_to_users_table.php',
            '2026_10_06_120000_add_admin_mfa_recovery.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        $this->withoutMiddleware(EnsureInstallationCompleted::class);
        Route::middleware(['web', 'auth'])->get('/_mfa-test/private', fn () => 'Private data');
    }

    protected function user(string $role = 'admin', bool $enrolled = false): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->forceFill(['admin_mfa_phone_1' => '+919876543210', 'admin_mfa_phone_1_verified_at' => now()])->save();
        $user->assignRole($role);
        if ($enrolled) {
            (new EnableTwoFactorAuthentication(app(TwoFactorAuthenticationProvider::class)))($user);
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        }

        return $user->refresh();
    }

    protected function passwordLogin(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    protected function code(string $encryptedSecret): string
    {
        return app(Google2FA::class)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($encryptedSecret));
    }
}
