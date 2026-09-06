<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the entire /admin/ai/* section behind the master "AI Enabled"
 * toggle in Settings > Branding (App\Http\Controllers\Admin\SettingController
 * ::updateAppBranding(), writes ai_enabled via AiSettingsService). When off,
 * the feature is fully stopped -- not just hidden from the sidebar (see
 * resources/views/admin/partials/sidebar.blade.php) -- direct URL access
 * redirects back with an explanation instead of showing a half-working page.
 */
class EnsureAiFeatureEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(AiSettingsService::class)->bool('ai_enabled')) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'The AI Control Center is currently disabled. Enable it from Settings → Branding.');
        }

        return $next($request);
    }
}
