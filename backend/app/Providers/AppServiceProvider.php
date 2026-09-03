<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\PaymentProviderRegistry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Providers are resolved from config, so approving a merchant account
        // later is a config line rather than a code change anywhere above the
        // PaymentProvider interface.
        $this->app->singleton(PaymentProviderRegistry::class, function ($app): PaymentProviderRegistry {
            $providers = array_map(
                fn (string $class): PaymentProvider => $app->make($class),
                config('payments.providers', []),
            );

            return new PaymentProviderRegistry($providers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // `MAIL_MAILER=brevo`. Registered here rather than pulled in as a
        // package because the whole integration is one HTTP call, and this
        // keeps the backend authenticating as the same sender the website
        // already uses successfully for this domain.
        Mail::extend('brevo', fn (array $config): BrevoApiTransport => new BrevoApiTransport(
            apiKey: (string) ($config['key'] ?? ''),
            timeout: (int) ($config['timeout'] ?? 20),
        ));

        // Forcing every route() call to root at APP_URL (.../admin) was tried
        // here once, to fix a guest redirected from a rewritten path like
        // /calls landing on WordPress's own /login. It over-corrected: the
        // mobile staff.* pages are reached through the exact same kind of
        // rewrite and their OWN links need the opposite root (no /admin at
        // all), so forcing one root broke the other's navigation instead.
        // There is no single global root correct for both; see
        // App\Support\AdminUrl for the call sites that need one stated
        // explicitly rather than inferred.
    }
}
