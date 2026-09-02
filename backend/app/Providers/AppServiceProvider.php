<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\PaymentProviderRegistry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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

        // In production the front controller sits in public_html/admin, and
        // WordPress rewrites clean paths like /calls onto it internally
        // (RewriteRule ... [L], no redirect). REQUEST_URI keeps the client's
        // original path through a rewrite like that, so anything Laravel
        // infers from the request — url(), route() — can come back rooted at
        // "/" instead of "/admin". That is invisible for most links, but
        // fatal for the ones the framework builds for itself: a guest
        // hitting a rewritten path getting redirected to route('login') was
        // sent to https://www.kcs.edu.za/login, which WordPress owns and
        // happily serves as its own login page — no error, just the wrong
        // one. APP_URL is stated correctly in every environment already
        // (KCS_WORKSPACE_URL and KCS_API_URL exist for the same reason), so
        // trusting it always, rather than letting each request's apparent
        // path override it, closes this off at the root instead of one call
        // site at a time.
        URL::forceRootUrl(config('app.url'));
    }
}
