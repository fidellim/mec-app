<?php

use App\Http\Middleware\EnsureSetupModeAllowsAccess;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'setup.mode' => EnsureSetupModeAllowsAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 429 && ! $request->expectsJson()) {
                $retryAfter = (int) $response->headers->get('Retry-After', 60);
                $seconds = max(1, $retryAfter);
                $message = 'Too many attempts. Please wait '.$seconds.' '.Str::plural('second', $seconds).' and try again.';

                if ($request->routeIs('admin.timesheets.export', 'admin.leave-plans.export', 'manage.audit-logs.export')) {
                    return back()->with('warning', $message);
                }

                $errorKey = $request->is('login', 'forgot-password', 'reset-password') ? 'email' : 'throttle';

                return back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors([
                        $errorKey => $message,
                    ]);
            }

            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Please sign in again.',
                ], 419);
            }

            auth()->guard()->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()
                ->route('login')
                ->with('status', 'Your session expired. Please sign in again.');
        });
    })->create();
