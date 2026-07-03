<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
        require base_path('routes/channels.php');

        try {
            MediaStorage::configure();
            $appName = AppSetting::getValue('app_name', Config::get('app.name'));
            $primaryColor = AppSetting::getValue('primary_color', '#8B5CF6');
            $secondaryColor = AppSetting::getValue('secondary_color', '#1E293B');
            $mailDriver = AppSetting::getValue('mail_driver', Config::get('mail.default'));
            $mailHost = AppSetting::getValue('mail_host', Config::get('mail.mailers.smtp.host'));
            $mailPort = AppSetting::getValue('mail_port', Config::get('mail.mailers.smtp.port'));
            $mailUsername = AppSetting::getValue('mail_username', Config::get('mail.mailers.smtp.username'));
            $mailPassword = AppSetting::getValue('mail_password', Config::get('mail.mailers.smtp.password'));
            $mailEncryption = AppSetting::getValue('mail_encryption', Config::get('mail.mailers.smtp.scheme'));
            $mailFromAddress = AppSetting::getValue('mail_from_address', Config::get('mail.from.address'));
            $mailFromName = AppSetting::getValue('mail_from_name', Config::get('mail.from.name'));
            $twilioSid = AppSetting::getValue('twilio_account_sid', '');
            $twilioToken = AppSetting::getValue('twilio_auth_token', '');
            $twilioPhone = AppSetting::getValue('twilio_phone_number', '');
            $broadcastConnection = AppSetting::getValue('broadcast_connection', Config::get('broadcasting.default', 'null'));
            $pusherConfig = [
                'app_id' => AppSetting::getValue('pusher_app_id', Config::get('broadcasting.connections.pusher.app_id')),
                'key' => AppSetting::getValue('pusher_app_key', Config::get('broadcasting.connections.pusher.key')),
                'secret' => AppSetting::getValue('pusher_app_secret', Config::get('broadcasting.connections.pusher.secret')),
                'cluster' => AppSetting::getValue('pusher_app_cluster', Config::get('broadcasting.connections.pusher.options.cluster', 'mt1')),
                'host' => AppSetting::getValue('pusher_host', Config::get('broadcasting.connections.pusher.options.host')),
                'port' => (int) AppSetting::getValue('pusher_port', Config::get('broadcasting.connections.pusher.options.port', 443)),
                'scheme' => AppSetting::getValue('pusher_scheme', Config::get('broadcasting.connections.pusher.options.scheme', 'https')),
            ];
            $firebaseEnabled = filter_var(AppSetting::getValue('firebase_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
            $googleMapsApiKey = AppSetting::getValue('google_maps_api_key', AppSetting::getValue('google_maps_key', ''));
            $defaultDeliveryRadius = AppSetting::getValue('default_delivery_radius', 10);
            $firebaseConfig = [
                'api_key' => AppSetting::getValue('firebase_api_key', ''),
                'auth_domain' => AppSetting::getValue('firebase_auth_domain', ''),
                'database_url' => AppSetting::getValue('firebase_database_url', ''),
                'project_id' => AppSetting::getValue('firebase_project_id', ''),
                'storage_bucket' => AppSetting::getValue('firebase_storage_bucket', ''),
                'messaging_sender_id' => AppSetting::getValue('firebase_messaging_sender_id', ''),
                'app_id' => AppSetting::getValue('firebase_app_id', ''),
                'server_key' => AppSetting::getValue('firebase_server_key', ''),
                'enabled' => $firebaseEnabled,
            ];
        } catch (\Throwable $e) {
            $appName = Config::get('app.name');
            $primaryColor = '#8B5CF6';
            $secondaryColor = '#1E293B';
            $mailDriver = Config::get('mail.default');
            $mailHost = Config::get('mail.mailers.smtp.host');
            $mailPort = Config::get('mail.mailers.smtp.port');
            $mailUsername = Config::get('mail.mailers.smtp.username');
            $mailPassword = Config::get('mail.mailers.smtp.password');
            $mailEncryption = Config::get('mail.mailers.smtp.scheme');
            $mailFromAddress = Config::get('mail.from.address');
            $mailFromName = Config::get('mail.from.name');
            $twilioSid = '';
            $twilioToken = '';
            $twilioPhone = '';
            $broadcastConnection = Config::get('broadcasting.default', 'null');
            $pusherConfig = [
                'app_id' => Config::get('broadcasting.connections.pusher.app_id'),
                'key' => Config::get('broadcasting.connections.pusher.key'),
                'secret' => Config::get('broadcasting.connections.pusher.secret'),
                'cluster' => Config::get('broadcasting.connections.pusher.options.cluster', 'mt1'),
                'host' => Config::get('broadcasting.connections.pusher.options.host'),
                'port' => Config::get('broadcasting.connections.pusher.options.port', 443),
                'scheme' => Config::get('broadcasting.connections.pusher.options.scheme', 'https'),
            ];
            $googleMapsApiKey = '';
            $defaultDeliveryRadius = 10;
            $firebaseConfig = [
                'api_key' => '',
                'auth_domain' => '',
                'database_url' => '',
                'project_id' => '',
                'storage_bucket' => '',
                'messaging_sender_id' => '',
                'app_id' => '',
                'server_key' => '',
                'enabled' => false,
            ];
        }

        $primaryDark = AppSetting::getValue('primary_dark', $this->shadeColor($primaryColor, -20));
        $primaryLight = AppSetting::getValue('primary_light', $this->shadeColor($primaryColor, 20));

        // Currency settings
        $currencySymbol = AppSetting::sanitizedCurrencySymbol();
        $currencyDecimals = (int) AppSetting::currencyDecimals();

        Config::set('app.name', $appName);
        Config::set('mail.default', $mailDriver);
        Config::set('mail.mailers.smtp.host', $mailHost);
        Config::set('mail.mailers.smtp.port', $mailPort);
        Config::set('mail.mailers.smtp.username', $mailUsername);
        Config::set('mail.mailers.smtp.password', $mailPassword);
        Config::set('mail.mailers.smtp.scheme', $mailEncryption);
        Config::set('mail.from.address', $mailFromAddress);
        Config::set('mail.from.name', $mailFromName);
        Config::set('services.twilio', [
            'sid' => $twilioSid,
            'token' => $twilioToken,
            'from' => $twilioPhone,
        ]);
        Config::set('broadcasting.default', $broadcastConnection);
        Config::set('broadcasting.connections.pusher.app_id', $pusherConfig['app_id']);
        Config::set('broadcasting.connections.pusher.key', $pusherConfig['key']);
        Config::set('broadcasting.connections.pusher.secret', $pusherConfig['secret']);
        Config::set('broadcasting.connections.pusher.options.cluster', $pusherConfig['cluster']);
        Config::set('broadcasting.connections.pusher.options.host', $pusherConfig['host'] ?: 'api-' . ($pusherConfig['cluster'] ?: 'mt1') . '.pusher.com');
        Config::set('broadcasting.connections.pusher.options.port', (int) ($pusherConfig['port'] ?: 443));
        Config::set('broadcasting.connections.pusher.options.scheme', $pusherConfig['scheme'] ?: 'https');
        Config::set('broadcasting.connections.pusher.options.encrypted', ($pusherConfig['scheme'] ?: 'https') === 'https');
        Config::set('broadcasting.connections.pusher.options.useTLS', ($pusherConfig['scheme'] ?: 'https') === 'https');
        Config::set('services.firebase', $firebaseConfig);

        $firebaseAccountPath = AppSetting::getValue('firebase_service_account_path', '');
        $firebaseCredentials = null;
        if (!empty($firebaseAccountPath)) {
            $firebaseAccountPath = ltrim($firebaseAccountPath, '/\\');
            if (Storage::disk('local')->exists($firebaseAccountPath)) {
                $firebaseCredentials = Storage::disk('local')->path($firebaseAccountPath);
            } else {
                $absolutePath = storage_path('app/' . $firebaseAccountPath);
                if (file_exists($absolutePath)) {
                    $firebaseCredentials = $absolutePath;
                }
            }
        }
        Config::set('firebase.credentials', $firebaseCredentials);

        Config::set('services.google_maps.key', $googleMapsApiKey);
        Config::set('app.default_delivery_radius', $defaultDeliveryRadius);

        View::share(compact('appName', 'primaryColor', 'primaryDark', 'primaryLight', 'secondaryColor', 'googleMapsApiKey', 'defaultDeliveryRadius', 'currencySymbol', 'currencyDecimals'));
    }

    protected function shadeColor(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/./', '$0$0', $hex);
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, (int) round($r + ($percent / 100) * 255)));
        $g = max(0, min(255, (int) round($g + ($percent / 100) * 255)));
        $b = max(0, min(255, (int) round($b + ($percent / 100) * 255)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
