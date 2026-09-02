<?php

use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'webhook.signature' => VerifyWebhookSignature::class,
        ]);

        // Filament owns the only login screen this app has, and it is not
        // named "login" — it is filament.admin.auth.login. Laravel's default
        // Authenticate middleware redirects an unauthenticated web request to
        // route('login'), which does not exist here, so any plain `auth`
        // -guarded route (the staff call queue among them) 500s the moment a
        // session expires instead of sending the person to sign back in.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
