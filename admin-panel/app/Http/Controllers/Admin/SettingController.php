<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FirebaseHelper;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\GatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class SettingController extends Controller
{
    public function index()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        return view('admin.settings.index', compact('settings'));
    }

    public function homepage()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        return view('admin.settings.homepage', compact('settings'));
    }

    public function privacy()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        return view('admin.settings.privacy', compact('settings'));
    }

    public function driverAssignment()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        return view('admin.settings.driver-assignment', compact('settings'));
    }

    public function communication()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        return view('admin.settings.communication', compact('settings'));
    }

    public function notifications()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();
        $firebaseDiagnostics = (new FirebaseHelper())->diagnostics();

        return view('admin.settings.notifications', compact('settings', 'firebaseDiagnostics'));
    }

    public function branding()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        return view('admin.settings.branding', compact('settings'));
    }

    public function payment()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();
        $paymentProviders = GatewayRegistry::paymentProviders();
        $payoutProviders = GatewayRegistry::payoutProviders();
        $customerGatewayProviders = GatewayRegistry::customerSelectablePaymentProviders();

        return view('admin.settings.payment', compact('settings', 'paymentProviders', 'payoutProviders', 'customerGatewayProviders'));
    }

    public function cron()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        return view('admin.settings.cron', [
            'settings' => $settings,
            'cronCommand' => PHP_OS_FAMILY === 'Windows'
                ? $this->windowsSchedulerCommand()
                : $this->schedulerCronCommand(),
            'scheduledTasks' => $this->scheduledTasks(),
            'canInstallCron' => class_exists(Process::class) && function_exists('proc_open'),
        ]);
    }

    public function installCron()
    {
        if (!class_exists(Process::class) || !function_exists('proc_open')) {
            return back()->with('error', 'This server has disabled process creation, so it cannot install scheduled tasks automatically. Ask the hosting provider to enable proc_open.');
        }

        try {
            $message = PHP_OS_FAMILY === 'Windows'
                ? $this->installWindowsScheduler()
                : $this->installUnixScheduler();
        } catch (\Throwable $exception) {
            return back()->with('error', 'Scheduler install failed: ' . $exception->getMessage());
        }

        AppSetting::updateOrCreate(
            ['key' => 'cron_installed_at'],
            ['value' => now()->toDateTimeString(), 'type' => 'string']
        );
        Cache::forget('app_settings');

        return back()->with('success', $message);
    }

    private function installWindowsScheduler(): string
    {
        $process = new Process([
            'schtasks.exe',
            '/Create',
            '/TN',
            'Swaad Laravel Scheduler',
            '/TR',
            $this->windowsTaskRunCommand(),
            '/SC',
            'MINUTE',
            '/MO',
            '1',
            '/F',
        ]);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return 'Windows scheduled task installed successfully and will run every minute.';
    }

    private function installUnixScheduler(): string
    {
        $cronLine = $this->schedulerCronCommand();
        $listProcess = new Process(['crontab', '-l']);
        $listProcess->setTimeout(15);
        $listProcess->run();
        $current = $listProcess->isSuccessful() ? $listProcess->getOutput() : '';

        if (str_contains($current, $cronLine)) {
            return 'Cron job is already installed.';
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'swaad-cron-');
        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create a temporary crontab file.');
        }

        try {
            File::put($tempPath, rtrim($current) . PHP_EOL . $cronLine . PHP_EOL);
            $installProcess = new Process(['crontab', $tempPath]);
            $installProcess->setTimeout(30);
            $installProcess->run();

            if (!$installProcess->isSuccessful()) {
                throw new \RuntimeException(trim($installProcess->getErrorOutput() ?: $installProcess->getOutput()));
            }
        } finally {
            File::delete($tempPath);
        }

        return 'Cron job installed successfully and will run every minute.';
    }

    public function map()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        if (empty($settings['google_maps_api_key']) && ! empty($settings['google_maps_key'])) {
            $settings['google_maps_api_key'] = $settings['google_maps_key'];
        }

        return view('admin.settings.map', compact('settings'));
    }
    
    public function update(Request $request)
    {
        $request->validate([
            'site_name' => 'nullable|string|max:255',
            'site_description' => 'nullable|string|max:2000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'media_storage_driver' => 'nullable|in:local,s3',
            'media_s3_key' => 'nullable|string|max:255',
            'media_s3_secret' => 'nullable|string|max:255',
            'media_s3_region' => 'nullable|string|max:100',
            'media_s3_bucket' => 'nullable|string|max:255',
            'media_s3_url' => 'nullable|url|max:500',
            'media_s3_endpoint' => 'nullable|url|max:500',
            'media_s3_path_style' => 'nullable|in:0,1',
            'firebase_service_account_json' => 'nullable|file|max:4096',
            'max_driver_assignment_attempts' => 'nullable|integer|min:1|max:200',
            'max_active_orders_per_driver' => 'nullable|integer|min:1|max:50',
            'driver_route_match_radius_km' => 'nullable|numeric|min:0.5|max:25',
            'driver_minimum_wallet_balance' => 'nullable|numeric|min:0|max:1000000',
            'message_service' => 'nullable|in:twilio,firebase',
            'otp_service_provider' => 'required_if:redirect_to,admin.settings.communication|in:twilio,firebase',
            'default_mobile_country_code' => 'nullable|string|max:8',
            'mail_driver' => 'nullable|in:smtp,log,array',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|numeric',
            'mail_encryption' => 'nullable|in:,tls,ssl',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'message_template_order_confirmation' => 'nullable|string|max:1000',
            'message_template_delivery_update' => 'nullable|string|max:1000',
            'message_template_otp' => 'nullable|string|max:1000',
            'twilio_account_sid' => 'nullable|string|max:255',
            'twilio_auth_token' => 'nullable|string|max:255',
            'twilio_phone_number' => 'nullable|string|max:25',
            'twilio_call_enabled' => 'nullable|in:0,1',
            'firebase_enabled' => 'nullable|in:0,1',
            'firebase_api_key' => 'nullable|string|max:255',
            'firebase_project_id' => 'nullable|string|max:255',
            'firebase_database_url' => 'nullable|string|max:255',
            'firebase_storage_bucket' => 'nullable|string|max:255',
            'firebase_messaging_sender_id' => 'nullable|string|max:255',
            'firebase_app_id' => 'nullable|string|max:255',
            'firebase_server_key' => 'nullable|string|max:255',
            'broadcast_connection' => 'nullable|in:null,log,pusher',
            'pusher_app_id' => 'nullable|string|max:255',
            'pusher_app_key' => 'nullable|string|max:255',
            'pusher_app_secret' => 'nullable|string|max:255',
            'pusher_app_cluster' => 'nullable|string|max:50',
            'pusher_host' => 'nullable|string|max:255',
            'pusher_port' => 'nullable|integer|min:1|max:65535',
            'pusher_scheme' => 'nullable|in:http,https',
            'social_login_enabled' => 'nullable|in:0,1',
            'social_login_google_enabled' => 'nullable|in:0,1',
            'social_login_apple_enabled' => 'nullable|in:0,1',
            'social_login_auto_register' => 'nullable|in:0,1',
            'social_login_auto_link_verified_email' => 'nullable|in:0,1',
            'social_login_google_web_client_id' => 'nullable|string|max:512',
            'social_login_apple_services_id' => 'nullable|string|max:255',
            'legal_terms' => 'nullable|string|max:5000',
            'legal_privacy' => 'nullable|string|max:5000',
            'legal_refund' => 'nullable|string|max:5000',
            'legal_contact_email' => 'nullable|email|max:255',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:255',
            'hero_location_placeholder' => 'nullable|string|max:255',
            'hero_search_placeholder' => 'nullable|string|max:255',
            'hero_search_button_text' => 'nullable|string|max:255',
            'partner_nav_text' => 'nullable|string|max:255',
            'partner_modal_title' => 'nullable|string|max:255',
            'partner_modal_subtitle' => 'nullable|string|max:255',
            'partner_restaurant_title' => 'nullable|string|max:255',
            'partner_restaurant_text' => 'nullable|string|max:255',
            'partner_driver_title' => 'nullable|string|max:255',
            'partner_driver_text' => 'nullable|string|max:255',
            'footer_description' => 'nullable|string|max:255',
            'footer_company_title' => 'nullable|string|max:255',
            'footer_support_title' => 'nullable|string|max:255',
            'footer_legal_title' => 'nullable|string|max:255',
            'footer_link_about' => 'nullable|string|max:255',
            'footer_link_careers' => 'nullable|string|max:255',
            'footer_link_blog' => 'nullable|string|max:255',
            'footer_link_help' => 'nullable|string|max:255',
            'footer_link_contact' => 'nullable|string|max:255',
            'footer_link_faqs' => 'nullable|string|max:255',
            'footer_copyright' => 'nullable|string|max:255',
            'category_section_title' => 'nullable|string|max:255',
            'category_section_subtitle' => 'nullable|string|max:255',
            'collection_section_title' => 'nullable|string|max:255',
            'collection_section_subtitle' => 'nullable|string|max:255',
            'restaurants_section_title' => 'nullable|string|max:255',
            'restaurants_section_subtitle' => 'nullable|string|max:255',
        ]);

        $redirectRoute = $request->input('redirect_to', 'admin.settings.index');
        $allowedRoutes = [
            'admin.settings.index',
            'admin.settings.homepage',
            'admin.settings.privacy',
            'admin.settings.driver_assignment',
            'admin.settings.communication',
            'admin.settings.notifications',
        ];

        if (! in_array($redirectRoute, $allowedRoutes)) {
            $redirectRoute = 'admin.settings.index';
        }

        if ($request->hasFile('firebase_service_account_json')) {
            $uploadedFile = $request->file('firebase_service_account_json');
            $contents = file_get_contents($uploadedFile->getRealPath());
            $serviceAccount = json_decode($contents, true);

            if (
                json_last_error() !== JSON_ERROR_NONE ||
                ! is_array($serviceAccount) ||
                empty($serviceAccount['project_id']) ||
                empty($serviceAccount['client_email']) ||
                empty($serviceAccount['private_key'])
            ) {
                return redirect()->route($redirectRoute)
                    ->withInput()
                    ->with('error', 'Firebase service account must be a valid Firebase Admin SDK JSON file.');
            }

            $path = 'firebase/service-account.json';

            if (! Storage::disk('local')->put($path, $contents)) {
                return redirect()->route($redirectRoute)
                    ->with('error', 'Firebase service account could not be saved. Please check storage permissions.');
            }

            if (! Storage::disk('local')->exists($path)) {
                return redirect()->route($redirectRoute)
                    ->with('error', 'Firebase service account could not be saved. Please check storage permissions.');
            }

            AppSetting::updateOrCreate([
                'key' => 'firebase_service_account_path'
            ], [
                'value' => $path,
                'type' => 'string',
            ]);
        }

        $settings = $request->except(['_token', 'firebase_service_account_json', 'redirect_to']);

        foreach (['mail_password', 'twilio_auth_token', 'firebase_api_key', 'firebase_project_id', 'firebase_database_url', 'firebase_storage_bucket', 'firebase_messaging_sender_id', 'firebase_app_id', 'firebase_server_key', 'pusher_app_secret', 'media_s3_secret'] as $sensitiveField) {
            if (array_key_exists($sensitiveField, $settings) && $settings[$sensitiveField] === '') {
                unset($settings[$sensitiveField]);
            }
        }

        if (array_key_exists('currency_decimals', $settings)) {
            $settings['currency_decimals'] = max(2, min(5, (int) $settings['currency_decimals']));
        }
        
        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->normalizeSettingValue($value),
                    'type' => $this->detectType($value),
                ]
            );
        }
        
        // Clear cache
        Cache::forget('app_settings');
        
        return redirect()->route($redirectRoute)
            ->with('success', 'Settings updated successfully!');
    }
    
    private function detectType($value)
    {
        if (is_array($value)) return 'json';
        if (is_bool($value)) return 'boolean';
        if (is_numeric($value)) return 'number';
        return 'string';
    }

    private function normalizeSettingValue($value)
    {
        return $value === null ? '' : $value;
    }
    
    public function updateAppBranding(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_logo' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
            'app_icon' => 'nullable|image|mimes:png,jpg,jpeg|max:512',
            'app_favicon' => 'nullable|file|mimes:ico,png,jpg,jpeg,webp,svg|max:512',
            'frontend_background_image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'header_branding_type' => 'nullable|in:text,logo,logo_text',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'restaurant_primary_color' => 'nullable|string|max:7',
            'restaurant_secondary_color' => 'nullable|string|max:7',
            'driver_primary_color' => 'nullable|string|max:7',
            'driver_secondary_color' => 'nullable|string|max:7',
            'customer_play_store_url' => 'nullable|url|max:500',
            'customer_deeplink_scheme' => 'nullable|string|max:80',
            'customer_deeplink_base_url' => 'nullable|url|max:500',
            'customer_order_deeplink_template' => 'nullable|string|max:500',
            'customer_restaurant_deeplink_template' => 'nullable|string|max:500',
            'customer_wallet_deeplink_template' => 'nullable|string|max:500',
            'onboarding_intro_title' => 'nullable|string|max:255',
            'onboarding_intro_subtitle' => 'nullable|string|max:500',
            'onboarding_slide_1_title' => 'nullable|string|max:255',
            'onboarding_slide_1_description' => 'nullable|string|max:500',
            'onboarding_slide_1_image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'onboarding_slide_2_title' => 'nullable|string|max:255',
            'onboarding_slide_2_description' => 'nullable|string|max:500',
            'onboarding_slide_2_image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'onboarding_slide_3_title' => 'nullable|string|max:255',
            'onboarding_slide_3_description' => 'nullable|string|max:500',
            'onboarding_slide_3_image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);
        
        if ($request->hasFile('app_logo')) {
            $path = $request->file('app_logo')->store('branding', 'public');
            AppSetting::updateOrCreate(['key' => 'app_logo'], ['value' => $path, 'type' => 'string']);
        }
        
        if ($request->hasFile('app_icon')) {
            $path = $request->file('app_icon')->store('branding', 'public');
            AppSetting::updateOrCreate(['key' => 'app_icon'], ['value' => $path, 'type' => 'string']);
        }

        if ($request->hasFile('app_favicon')) {
            $path = $request->file('app_favicon')->store('branding', 'public');
            AppSetting::updateOrCreate(['key' => 'app_favicon'], ['value' => $path, 'type' => 'string']);
        }

        if ($request->hasFile('frontend_background_image')) {
            $path = $request->file('frontend_background_image')->store('branding', 'public');
            AppSetting::updateOrCreate(['key' => 'frontend_background_image'], ['value' => $path, 'type' => 'string']);
        }
        
        AppSetting::updateOrCreate(['key' => 'app_name'], ['value' => $request->app_name, 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'header_branding_type'], ['value' => $request->header_branding_type ?? 'text', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'primary_color'], ['value' => $request->primary_color ?? '#6366f1', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'secondary_color'], ['value' => $request->secondary_color ?? '#8b5cf6', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'restaurant_primary_color'], ['value' => $request->restaurant_primary_color ?? ($request->primary_color ?? '#0A9443'), 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'restaurant_secondary_color'], ['value' => $request->restaurant_secondary_color ?? ($request->secondary_color ?? '#0C7038'), 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'driver_primary_color'], ['value' => $request->driver_primary_color ?? ($request->primary_color ?? '#0A9443'), 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'driver_secondary_color'], ['value' => $request->driver_secondary_color ?? ($request->secondary_color ?? '#0C7038'), 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'customer_play_store_url'], ['value' => $request->customer_play_store_url ?? '', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'customer_deeplink_scheme'], ['value' => $request->customer_deeplink_scheme ?? 'foodflow', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'customer_deeplink_base_url'], ['value' => $request->customer_deeplink_base_url ?? '', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'customer_order_deeplink_template'], ['value' => $request->customer_order_deeplink_template ?? 'foodflow://orders/{order_id}', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'customer_restaurant_deeplink_template'], ['value' => $request->customer_restaurant_deeplink_template ?? 'foodflow://restaurants/{restaurant_id}', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'customer_wallet_deeplink_template'], ['value' => $request->customer_wallet_deeplink_template ?? 'foodflow://wallet', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'onboarding_intro_title'], ['value' => $request->onboarding_intro_title ?? '', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'onboarding_intro_subtitle'], ['value' => $request->onboarding_intro_subtitle ?? '', 'type' => 'string']);

        foreach ([1, 2, 3] as $index) {
            AppSetting::updateOrCreate(
                ['key' => "onboarding_slide_{$index}_title"],
                ['value' => $request->input("onboarding_slide_{$index}_title", ''), 'type' => 'string']
            );
            AppSetting::updateOrCreate(
                ['key' => "onboarding_slide_{$index}_description"],
                ['value' => $request->input("onboarding_slide_{$index}_description", ''), 'type' => 'string']
            );

            if ($request->hasFile("onboarding_slide_{$index}_image")) {
                $path = $request->file("onboarding_slide_{$index}_image")->store('branding', 'public');
                AppSetting::updateOrCreate(
                    ['key' => "onboarding_slide_{$index}_image"],
                    ['value' => $path, 'type' => 'string']
                );
            }
        }

        Cache::forget('app_settings');
        
        return redirect()->route('admin.settings.index')
            ->with('success', 'App branding updated successfully!');
    }
    
    public function updatePaymentSettings(Request $request)
    {
        $request->validate([
            'payment_gateway_enabled' => 'nullable|in:0,1',
            'cod_enabled' => 'nullable|in:0,1',
            'payment_gateway_provider' => 'nullable|in:' . implode(',', array_keys(GatewayRegistry::paymentProviders())),
            'enabled_payment_gateways' => 'nullable|array|min:1',
            'enabled_payment_gateways.*' => 'nullable|in:' . implode(',', array_keys(GatewayRegistry::customerSelectablePaymentProviders())),
            'payout_gateway_provider' => 'nullable|in:' . implode(',', array_keys(GatewayRegistry::payoutProviders())),
            'auto_payout_enabled' => 'nullable|in:0,1',
            'payment_gateway_logo' => 'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'payment_gateway_logo_razorpay' => 'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'payment_gateway_logo_stripe' => 'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'payment_gateway_logo_cashfree' => 'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'country_code' => 'nullable|string|max:16',
            'currency_code' => 'nullable|string|max:8',
            'currency_symbol' => 'nullable|string|max:8',
            'currency_decimals' => 'nullable|integer|min:2|max:5',
            'customer_deeplink_base_url' => 'nullable|string|max:2048',
            'razorpay_key' => 'nullable|string',
            'razorpay_secret' => 'nullable|string',
            'razorpay_mode' => 'nullable|in:test,live',
            'razorpay_x_account_number' => 'nullable|string',
            'stripe_key' => 'nullable|string',
            'stripe_secret' => 'nullable|string',
            'stripe_mode' => 'nullable|in:test,live',
            'stripe_webhook_secret' => 'nullable|string',
            'cashfree_key' => 'nullable|string',
            'cashfree_secret' => 'nullable|string',
            'cashfree_mode' => 'nullable|in:test,live',
            'paystack_public_key' => 'nullable|string',
            'paystack_secret_key' => 'nullable|string',
            'paystack_mode' => 'nullable|in:test,live',
            'sslcommerz_store_id' => 'nullable|string',
            'sslcommerz_store_password' => 'nullable|string',
            'sslcommerz_mode' => 'nullable|in:test,live',
            'mollie_key' => 'nullable|string',
            'mollie_profile_id' => 'nullable|string',
            'mollie_mode' => 'nullable|in:test,live',
            'senangpay_merchant_id' => 'nullable|string',
            'senangpay_secret_key' => 'nullable|string',
            'senangpay_mode' => 'nullable|in:test,live',
            'bkash_app_key' => 'nullable|string',
            'bkash_app_secret' => 'nullable|string',
            'bkash_username' => 'nullable|string',
            'bkash_password' => 'nullable|string',
            'bkash_mode' => 'nullable|in:test,live',
            'mercadopago_public_key' => 'nullable|string',
            'mercadopago_access_token' => 'nullable|string',
            'mercadopago_mode' => 'nullable|in:test,live',
            'skrill_merchant_email' => 'nullable|string',
            'skrill_secret_word' => 'nullable|string',
            'skrill_mode' => 'nullable|in:test,live',
            'easypaisa_store_id' => 'nullable|string',
            'easypaisa_hash_key' => 'nullable|string',
            'easypaisa_mode' => 'nullable|in:test,live',
        ]);
        
        $settings = $request->only([
            'payment_gateway_enabled',
            'cod_enabled',
            'payment_gateway_provider',
            'payout_gateway_provider',
            'auto_payout_enabled',
            'country_code',
            'currency_code',
            'currency_symbol',
            'currency_decimals',
            'customer_deeplink_base_url',
            'razorpay_key',
            'razorpay_secret',
            'razorpay_mode',
            'razorpay_x_account_number',
            'stripe_key',
            'stripe_secret',
            'stripe_mode',
            'stripe_webhook_secret',
            'cashfree_key',
            'cashfree_secret',
            'cashfree_mode',
            'paystack_public_key',
            'paystack_secret_key',
            'paystack_mode',
            'sslcommerz_store_id',
            'sslcommerz_store_password',
            'sslcommerz_mode',
            'mollie_key',
            'mollie_profile_id',
            'mollie_mode',
            'senangpay_merchant_id',
            'senangpay_secret_key',
            'senangpay_mode',
            'bkash_app_key',
            'bkash_app_secret',
            'bkash_username',
            'bkash_password',
            'bkash_mode',
            'mercadopago_public_key',
            'mercadopago_access_token',
            'mercadopago_mode',
            'skrill_merchant_email',
            'skrill_secret_word',
            'skrill_mode',
            'easypaisa_store_id',
            'easypaisa_hash_key',
            'easypaisa_mode',
        ]);

        $enabledGateways = array_values(array_filter(
            $request->input('enabled_payment_gateways', []),
            fn ($gateway) => array_key_exists(
                strtolower((string) $gateway),
                GatewayRegistry::customerSelectablePaymentProviders()
            )
        ));

        if (empty($enabledGateways)) {
            $fallbackGateway = strtolower((string) ($settings['payment_gateway_provider'] ?? 'razorpay'));
            $enabledGateways = array_key_exists(
                $fallbackGateway,
                GatewayRegistry::customerSelectablePaymentProviders()
            ) ? [$fallbackGateway] : ['razorpay'];
        }

        $settings['enabled_payment_gateways'] = json_encode($enabledGateways);

        if (array_key_exists('currency_decimals', $settings)) {
            $settings['currency_decimals'] = max(2, min(5, (int) $settings['currency_decimals']));
        }

        foreach ([
            'razorpay_secret',
            'stripe_secret',
            'stripe_webhook_secret',
            'cashfree_secret',
            'paystack_secret_key',
            'sslcommerz_store_password',
            'mollie_key',
            'senangpay_secret_key',
            'bkash_app_secret',
            'bkash_password',
            'mercadopago_access_token',
            'skrill_secret_word',
            'easypaisa_hash_key',
        ] as $sensitiveField) {
            if (array_key_exists($sensitiveField, $settings) && $settings[$sensitiveField] === '') {
                unset($settings[$sensitiveField]);
            }
        }

        if ($request->hasFile('payment_gateway_logo')) {
            $existingLogo = AppSetting::getValue('payment_gateway_logo');
            if ($existingLogo) {
                Storage::disk('public')->delete($existingLogo);
            }

            $logoPath = $request->file('payment_gateway_logo')->store('branding/payment-gateways', 'public');
            AppSetting::updateOrCreate(
                ['key' => 'payment_gateway_logo'],
                ['value' => $logoPath, 'type' => 'string']
            );
        }

        foreach (array_keys(GatewayRegistry::customerSelectablePaymentProviders()) as $gateway) {
            $field = 'payment_gateway_logo_' . $gateway;
            if (!$request->hasFile($field)) {
                continue;
            }

            $settingKey = 'payment_gateway_logo_' . $gateway;
            $existingLogo = AppSetting::getValue($settingKey);
            if ($existingLogo) {
                Storage::disk('public')->delete($existingLogo);
            }

            $logoPath = $request->file($field)->store('branding/payment-gateways', 'public');
            AppSetting::updateOrCreate(
                ['key' => $settingKey],
                ['value' => $logoPath, 'type' => 'string']
            );
        }

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate([
                'key' => $key
            ], [
                'value' => $this->normalizeSettingValue($value),
                'type' => 'string'
            ]);
        }

        Cache::forget('app_settings');

        return redirect()->route('admin.settings.index')
            ->with('success', 'Payment settings updated successfully!');
    }

    private function schedulerCronCommand(): string
    {
        $php = escapeshellarg($this->phpCliPath());
        $projectPath = escapeshellarg(base_path());

        return "* * * * * cd {$projectPath} && {$php} artisan schedule:run >> /dev/null 2>&1";
    }

    private function windowsSchedulerCommand(): string
    {
        return 'schtasks /Create /TN "Swaad Laravel Scheduler" /TR "'
            . $this->windowsTaskRunCommand()
            . '" /SC MINUTE /MO 1 /F';
    }

    private function windowsTaskRunCommand(): string
    {
        return '"' . $this->phpCliPath() . '" "' . base_path('artisan') . '" schedule:run';
    }

    private function phpCliPath(): string
    {
        $binary = PHP_BINDIR . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');

        return is_file($binary) ? $binary : (PHP_OS_FAMILY === 'Windows' ? PHP_BINARY : '/usr/bin/php');
    }

    private function scheduledTasks(): array
    {
        $path = base_path('routes/console.php');
        if (!File::exists($path)) {
            return [];
        }

        $lines = preg_split('/\R/', File::get($path));
        $tasks = [];
        $comment = null;

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '//')) {
                $comment = trim(substr($trimmed, 2));
                continue;
            }

            if (!str_contains($trimmed, 'Schedule::')) {
                continue;
            }

            $statement = $trimmed;
            if (!str_ends_with($trimmed, ';')) {
                for ($next = $index + 1; $next < count($lines); $next++) {
                    $statement .= ' ' . trim($lines[$next]);
                    if (str_ends_with(trim($lines[$next]), ';')) {
                        break;
                    }
                }
            }

            preg_match_all('/->([a-zA-Z0-9_]+)\((.*?)\)/', $statement, $methodMatches, PREG_SET_ORDER);
            preg_match("/Schedule::command\\('([^']+)'\\)/", $statement, $commandMatch);
            $frequencyMatch = end($methodMatches) ?: [];

            $tasks[] = [
                'name' => $comment ?: ($commandMatch[1] ?? 'Scheduled callback'),
                'type' => str_contains($statement, 'Schedule::command') ? 'Command' : 'Callback',
                'command' => $commandMatch[1] ?? 'Closure / service callback',
                'frequency' => $frequencyMatch[1] ?? 'custom',
                'expression' => $frequencyMatch[2] ?? '',
            ];

            $comment = null;
        }

        return $tasks;
    }
}
