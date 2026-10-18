<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\AppDownloadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public side of the single-QR app download flow.
 *
 * /app            -> customer app, device detected
 * /app/{app}      -> restaurant / driver app
 * /app/qr.svg     -> the QR image for any of them (?app=, ?size=)
 */
class AppDownloadController extends Controller
{
    public function __construct(private readonly AppDownloadService $downloads) {}

    public function show(Request $request, ?string $app = null)
    {
        if (! $this->downloads->enabled()) {
            abort(404);
        }

        $app = $this->downloads->normalizeApp($app);
        $platform = $this->downloads->detectPlatform($request->userAgent());
        $storeUrl = $this->downloads->storeUrlForPlatform($app, $platform);

        // A phone we recognise and a store link we have: straight through.
        if ($storeUrl !== null) {
            return $this->noStore(redirect()->away($storeUrl, 302));
        }

        // Desktop, when the admin prefers a plain bounce to the website.
        if ($platform === AppDownloadService::PLATFORM_OTHER
            && $this->downloads->desktopMode() === 'redirect'
        ) {
            $fallback = $this->downloads->fallbackUrl();
            if ($fallback !== '') {
                return $this->noStore(redirect()->away($fallback, 302));
            }
        }

        // Everything else -- desktop, link-preview crawlers, or a phone whose
        // store link is not configured yet -- gets the landing page.
        return $this->noStore(response()->view('app-download', $this->landingData($app, $platform)));
    }

    public function qrSvg(Request $request)
    {
        $app = $this->downloads->normalizeApp($request->query('app'));
        $size = (int) $request->query('size', 320);

        $svg = $this->downloads->qrSvg($this->downloads->shortUrl($app), $size);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=300',
            'Content-Disposition' => $this->disposition($request, $app, 'svg'),
        ]);
    }

    public function qrPng(Request $request)
    {
        $app = $this->downloads->normalizeApp($request->query('app'));
        $size = (int) $request->query('size', 1024);

        $png = $this->downloads->qrPng($this->downloads->shortUrl($app), $size);

        if ($png === null) {
            // No GD on this server -- hand back the vector instead of a 500.
            return redirect()->route('app.download.qr', array_filter([
                'app' => $app === AppDownloadService::DEFAULT_APP ? null : $app,
                'size' => $size,
                'download' => $request->query('download'),
            ]));
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=300',
            'Content-Disposition' => $this->disposition($request, $app, 'png'),
        ]);
    }

    private function disposition(Request $request, string $app, string $extension): string
    {
        $name = str($this->siteName() . '-' . $app . '-app-qr')->slug() . '.' . $extension;

        return ($request->boolean('download') ? 'attachment' : 'inline') . '; filename="' . $name . '"';
    }

    private function landingData(string $app, string $platform): array
    {
        $settings = AppSetting::all()->pluck('value', 'key')->toArray();

        return [
            'app' => $app,
            'appLabel' => $this->downloads->label($app),
            'platform' => $platform,
            'androidUrl' => $this->downloads->androidUrl($app),
            'iosUrl' => $this->downloads->iosUrl($app),
            'websiteUrl' => $this->downloads->fallbackUrl(),
            'shortUrl' => $this->downloads->shortUrl($app),
            'qrUrl' => route('app.download.qr', array_filter([
                'app' => $app === AppDownloadService::DEFAULT_APP ? null : $app,
                'size' => 420,
            ])),
            'settings' => $settings,
        ];
    }

    /**
     * The response is user-agent specific, so shared caches (CDN, corporate
     * proxy) must not hand an Android redirect to the next iPhone.
     */
    private function noStore(Response|\Symfony\Component\HttpFoundation\Response $response)
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Vary', 'User-Agent');

        return $response;
    }

    private function siteName(): string
    {
        $name = trim((string) AppSetting::getValue('site_name', ''));

        if ($name === '') {
            $name = trim((string) AppSetting::getValue('app_name', ''));
        }

        return $name !== '' ? $name : (string) config('app.name', 'app');
    }
}
