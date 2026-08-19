<?php

namespace App\Providers;

use App\Auth\Passwords\AtomicPasswordBrokerManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('auth.password', function ($manager, $app) {
            return new AtomicPasswordBrokerManager($app);
        });
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        RateLimiter::for('login', function (Request $request) {
            if (config('app.env') === 'e2e' && env('E2E_DISABLE_LOGIN_THROTTLE', false)) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($this->emailIpKey($request));
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinute(3)->by($this->emailIpKey($request));
        });

        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('authenticated-writes', function (Request $request) {
            return Limit::perMinute(60)->by($this->userIpKey($request));
        });

        RateLimiter::for('workflow-actions', function (Request $request) {
            return Limit::perMinute(30)->by($this->userIpKey($request));
        });

        RateLimiter::for('manual-reminders', function (Request $request) {
            return Limit::perMinute(5)->by($this->userIpKey($request));
        });

        RateLimiter::for('exports', function (Request $request) {
            if (config('app.env') === 'e2e' && env('E2E_DISABLE_EXPORT_THROTTLE', false)) {
                return Limit::none();
            }

            return Limit::perMinute(6)->by($this->userIpKey($request));
        });
    }

    private function emailIpKey(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email'))).'|'.$request->ip();
    }

    private function userIpKey(Request $request): string
    {
        return ($request->user()?->id ?? 'guest').'|'.$request->ip();
    }
}
