<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Serves the two /.well-known files an operating system fetches before it
 * will let this domain's links open the mobile app instead of the browser.
 *
 * Used by the movie-theatre seat QR stickers: a scanned armrest sticker is
 * an ordinary https link, so a customer without the app still gets the web
 * ordering page, while a customer with the app lands straight on their seat
 * inside it. That fallback is the reason the sticker encodes a real URL and
 * not a custom scheme.
 *
 * Both files must be served over HTTPS, from the domain root, with no
 * redirect and no authentication — the OS fetches them itself and is strict
 * about all three. See config/applinks.php.
 */
class AppLinksController extends Controller
{
    /**
     * Android App Links: which app may claim this domain's links.
     *
     * Path scoping is NOT expressible here; it lives in the app's
     * AndroidManifest intent-filter (scoped to /t/). This file only answers
     * "is this app allowed to speak for this domain at all".
     */
    public function assetLinks(): JsonResponse
    {
        $fingerprints = collect(explode(',', (string) config('applinks.android.sha256_fingerprints')))
            ->map(fn (string $fingerprint) => strtoupper(trim($fingerprint)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($fingerprints === []) {
            Log::warning('assetlinks.json requested but no signing fingerprints are configured.');

            return response()->json([], 404);
        }

        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => config('applinks.android.package'),
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * iOS Universal Links.
     *
     * Unlike Android, the path scoping lives HERE, so only the seat-sticker
     * paths open the app; the marketing site, the legal pages and the admin
     * panel stay in the browser.
     *
     * Must be served as application/json with no .json extension on the
     * path, which is why this is a route rather than a static public file.
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        $teamId = trim((string) config('applinks.ios.team_id'));
        $bundleId = trim((string) config('applinks.ios.bundle_id'));

        // Serving a wrong app id is worse than serving nothing: iOS caches
        // this file, so a bad copy breaks universal links for everyone who
        // fetched it, long after the mistake is fixed.
        if ($teamId === '' || $bundleId === '') {
            Log::warning('apple-app-site-association requested but IOS_APP_TEAM_ID is not configured.');

            return response()->json([], 404);
        }

        $appId = $teamId . '.' . $bundleId;
        $paths = array_values((array) config('applinks.paths', []));

        return response()->json([
            'applinks' => [
                'details' => [
                    [
                        'appIDs' => [$appId],
                        'components' => array_map(
                            fn (string $path) => ['/' => $path, 'comment' => 'In-seat ordering from a scanned theatre seat sticker'],
                            $paths
                        ),
                    ],
                ],
            ],
            // Kept for iOS versions that still read the legacy shape.
            'webcredentials' => [
                'apps' => [$appId],
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
