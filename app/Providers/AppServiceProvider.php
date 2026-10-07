<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('ai', fn (Request $request): Limit => Limit::perMinute(20)->by(
            $request->user()?->id ?: $request->ip(),
        ));

        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(10)->by(
            $request->user()?->id ?: $request->ip(),
        ));
    }
}
