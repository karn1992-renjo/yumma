<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EnsureInstallationCompleted
{
    private const LOCK_FILE = 'installed.lock';

    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        if ($this->isInstalled()) {
            return $next($request);
        }

        return redirect()->route('install.show');
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->is('install') || $request->is('install/*')) {
            return true;
        }

        if ($request->is('up')) {
            return true;
        }

        return $request->is('build/*')
            || $request->is('storage/*')
            || $request->is('favicon.ico')
            || $request->is('robots.txt');
    }

    private function isInstalled(): bool
    {
        return File::exists(storage_path('app/' . self::LOCK_FILE));
    }
}
