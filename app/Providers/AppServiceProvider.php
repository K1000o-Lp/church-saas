<?php

namespace App\Providers;

use App\Support\Tenancy\CurrentChurch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentChurch::class, fn () => new CurrentChurch);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(10)->letters()->numbers());

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('admin-login', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('password-email', fn (Request $request): array => [
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perMinute(3)->by('email:'.hash('sha256', Str::lower((string) $request->input('email')))),
        ]);

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('church-onboarding', fn (Request $request): Limit => Limit::perHour(5)
            ->by('user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('church-invitations', fn (Request $request): Limit => Limit::perHour(30)
            ->by('user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('verification-send', fn (Request $request): Limit => Limit::perMinute(6)
            ->by('user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
