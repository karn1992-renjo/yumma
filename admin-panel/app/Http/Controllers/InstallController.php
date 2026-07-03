<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InstallController extends Controller
{
    private const LOCK_FILE = 'installed.lock';

    public function show(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect()->route('login');
        }

        session(['installer.license_verified' => true]);

        $step = max(0, min(3, (int) $request->query('step', 0)));

        return view('install.index', [
            'step' => $step,
            'requirements' => $this->requirements(),
            'licenseVerified' => (bool) session('installer.license_verified', false),
            'databaseReady' => (bool) session('installer.database_ready', false),
            'defaultPackages' => [
                'customer' => 'com.example.app',
                'driver' => 'com.example.foodflow_driver',
                'restaurant' => 'com.example.app_vendor',
            ],
        ]);
    }

    public function verifyLicense(Request $request)
    {
        session([
            'installer.license_verified' => true,
            'installer.purchase_code' => '',
            'installer.buyer_name' => '',
            'installer.license_response' => [],
        ]);

        return redirect()->route('install.show', ['step' => 2]);
    }

    public function setupDatabase(Request $request)
    {
        $validated = $request->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_name' => ['required', 'string', 'max:255'],
            'db_user' => ['required', 'string', 'max:255'],
            'db_pass' => ['nullable', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
        ]);

        try {
            $this->testDatabaseConnection($validated);
            $this->writeEnvFile($validated, $request);
            $this->applyRuntimeDatabaseConfig($validated);

            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
        } catch (Throwable $e) {
            return back()->withErrors([
                'db_name' => $e->getMessage(),
            ]);
        }

        if (class_exists(AppSetting::class)) {
            AppSetting::setValue('purchase_code_verified', '1');
            AppSetting::setValue('purchase_code_verified_at', now()->toDateTimeString());
            AppSetting::setValue('purchase_access_checked_at', now()->toDateTimeString());
        }

        session(['installer.database_ready' => true]);
        $this->markInstalled();

        return redirect()->route('install.show', ['step' => 3]);
    }

    public function complete(Request $request)
    {
        if (! $this->isInstalled()) {
            return redirect()->route('install.show');
        }

        return redirect()->route('login');
    }

    private function requirements(): array
    {
        $extensions = ['bcmath', 'ctype', 'curl', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'];

        return [
            'php_version' => PHP_VERSION,
            'php_ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'extensions' => array_map(static function (string $extension): array {
                return [
                    'name' => $extension,
                    'loaded' => extension_loaded($extension),
                ];
            }, $extensions),
            'writable_storage' => is_writable(storage_path()),
            'writable_bootstrap_cache' => is_writable(base_path('bootstrap/cache')),
        ];
    }

    private function testDatabaseConnection(array $validated): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $validated['db_host'],
            (int) $validated['db_port'],
            $validated['db_name']
        );

        new \PDO($dsn, $validated['db_user'], (string) ($validated['db_pass'] ?? ''), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    private function writeEnvFile(array $validated, Request $request): void
    {
        $envPath = base_path('.env');
        $content = File::exists($envPath)
            ? File::get($envPath)
            : File::get(base_path('.env.example'));

        $replacements = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $request->getSchemeAndHttpHost(),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $validated['db_host'],
            'DB_PORT' => (string) $validated['db_port'],
            'DB_DATABASE' => $validated['db_name'],
            'DB_USERNAME' => $validated['db_user'],
            'DB_PASSWORD' => (string) ($validated['db_pass'] ?? ''),
        ];

        foreach ($replacements as $key => $value) {
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
            $line = $key . '=' . $this->formatEnvValue($value);

            if (preg_match($pattern, $content) === 1) {
                $content = preg_replace($pattern, $line, $content);
            } else {
                $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
            }
        }

        File::put($envPath, $content);
    }

    private function applyRuntimeDatabaseConfig(array $validated): void
    {
        config([
            'database.connections.mysql.host' => $validated['db_host'],
            'database.connections.mysql.port' => $validated['db_port'],
            'database.connections.mysql.database' => $validated['db_name'],
            'database.connections.mysql.username' => $validated['db_user'],
            'database.connections.mysql.password' => $validated['db_pass'] ?? '',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function markInstalled(): void
    {
        File::put(storage_path('app/' . self::LOCK_FILE), now()->toIso8601String());
    }

    private function isInstalled(): bool
    {
        return File::exists(storage_path('app/' . self::LOCK_FILE));
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s/', $value) === 1 || str_contains($value, '#')) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return $value;
    }
}
