<?php

namespace App\Providers;

use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\PaymentProviderRegistry;
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
        //
    }
}
