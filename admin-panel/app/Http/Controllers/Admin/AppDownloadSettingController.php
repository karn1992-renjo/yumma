<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AppDownloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Settings -> App Download QR.
 *
 * The admin stores a package name / App Store id per app; everything the
 * printed QR needs is derived from that, so a store link change never
 * invalidates a code that is already on a flyer.
 */
class AppDownloadSettingController extends Controller
{
    public function __construct(private readonly AppDownloadService $downloads) {}

    public function edit()
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        $apps = [];
        foreach (array_keys(AppDownloadService::APPS) as $app) {
            $apps[$app] = $this->downloads->summary($app);
        }

        return view('admin.settings.app-download', [
            'settings' => $settings,
            'apps' => $apps,
            'enabled' => $this->downloads->enabled(),
            'desktopMode' => $this->downloads->desktopMode(),
            'fallbackUrl' => $this->downloads->fallbackUrl(),
            'qrColor' => $this->downloads->qrColor(),
            'qrLogoEnabled' => $this->downloads->qrLogoEnabled(),
            'pngAvailable' => extension_loaded('gd'),
            'cacheBuster' => substr(md5(serialize($apps) . $this->downloads->qrColor() . (int) $this->downloads->qrLogoEnabled()), 0, 10),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'app_download_enabled' => 'nullable|in:0,1',
            'app_download_desktop_mode' => 'required|in:landing,redirect',
            'app_download_fallback_url' => 'nullable|url|max:500',
            'app_download_qr_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'app_download_qr_logo' => 'nullable|in:0,1',
        ];

        foreach (array_keys(AppDownloadService::APPS) as $app) {
            $rules[$app . '_android_package'] = 'nullable|string|max:255';
            $rules[$app . '_ios_app_id'] = 'nullable|string|max:255';
            $rules[$app . '_android_store_url'] = 'nullable|url|max:500';
            $rules[$app . '_ios_store_url'] = 'nullable|url|max:500';
        }

        $request->validate($rules, [], [
            'app_download_qr_color' => 'QR colour',
        ]);

        $values = [
            'app_download_enabled' => $request->input('app_download_enabled', '0'),
            'app_download_desktop_mode' => $request->input('app_download_desktop_mode', 'landing'),
            'app_download_fallback_url' => trim((string) $request->input('app_download_fallback_url', '')),
            'app_download_qr_color' => strtolower(trim((string) $request->input('app_download_qr_color', '#000000'))) ?: '#000000',
            'app_download_qr_logo' => $request->input('app_download_qr_logo', '0'),
        ];

        $rejected = [];

        foreach (array_keys(AppDownloadService::APPS) as $app) {
            $rawPackage = trim((string) $request->input($app . '_android_package', ''));
            $package = AppDownloadService::normalizeAndroidPackage($rawPackage);
            if ($rawPackage !== '' && $package === '') {
                $rejected[] = AppDownloadService::APPS[$app] . ': "' . $rawPackage . '" is not a valid Android package name.';
            }

            $rawAppId = trim((string) $request->input($app . '_ios_app_id', ''));
            $appId = AppDownloadService::normalizeIosAppId($rawAppId);
            if ($rawAppId !== '' && $appId === '') {
                $rejected[] = AppDownloadService::APPS[$app] . ': "' . $rawAppId . '" is not a valid App Store id.';
            }

            $values[$app . '_android_package'] = $package;
            $values[$app . '_ios_app_id'] = $appId;
            $values[$app . '_android_store_url'] = trim((string) $request->input($app . '_android_store_url', ''));
            $values[$app . '_ios_store_url'] = trim((string) $request->input($app . '_ios_store_url', ''));
        }

        if ($rejected !== []) {
            return redirect()->route('admin.settings.app-download')
                ->withInput()
                ->with('error', implode(' ', $rejected));
        }

        // Read before writing: the cached SVGs are keyed by the colour that
        // was in force when they were rendered.
        $previousColor = $this->downloads->qrColor();

        foreach ($values as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value === null ? '' : (string) $value, 'type' => 'string']
            );
        }

        Cache::forget('app_settings');
        $this->forgetQrCache([$previousColor, $values['app_download_qr_color']]);

        return redirect()->route('admin.settings.app-download')
            ->with('success', 'App download links updated. The printed QR codes keep working -- they point at this server, not at the stores.');
    }

    /**
     * QR SVGs are cached by url + size + colour. The encoded url never changes,
     * so clearing the sizes this panel and the landing page request, for both
     * the old and the new colour, covers every live entry.
     *
     * @param  array<int, string>  $colors
     */
    private function forgetQrCache(array $colors): void
    {
        foreach (array_keys(AppDownloadService::APPS) as $app) {
            $url = $this->downloads->shortUrl($app);

            foreach (array_unique($colors) as $color) {
                foreach ([210, 320, 420, 640, 1024] as $size) {
                    Cache::forget('app_download_qr_svg:' . md5($url . '|' . $size . '|' . $color));
                }
            }
        }
    }
}
