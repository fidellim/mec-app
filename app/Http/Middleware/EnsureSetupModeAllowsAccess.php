<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupModeAllowsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SystemSetting::setupModeEnabled()) {
            return $next($request);
        }

        if ($request->routeIs('setup.in-progress') || $request->routeIs('logout')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && in_array($user->role, ['admin', 'super_admin'], true)) {
            return $next($request);
        }

        return redirect()->route('setup.in-progress');
    }
}
