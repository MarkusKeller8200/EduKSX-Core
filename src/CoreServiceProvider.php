<?php

namespace EduKSX\Core;

use EduKSX\Core\Http\Middleware\AssignRequestId;
use EduKSX\Core\Http\Middleware\SecurityHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Bindet eduksx/core in ein Modul ein (Package Discovery): Konfiguration, Request-ID und Sicherheitskopfzeilen für
 * alle Anfragen, Abruflimit «api» (API-Standard C5). Fehler als problem+json bindet das Modul in bootstrap/app.php
 * ein: ->withExceptions(fn ($e) => \EduKSX\Core\Http\Problem::register($e)).
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eduksx.php', 'eduksx');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/eduksx.php' => config_path('eduksx.php')], 'eduksx-config');

        $module = config('eduksx.module');
        if (is_string($module) && $module !== '') {
            Module::assertValid($module);
        }

        $kernel = $this->app->make(Kernel::class);
        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(AssignRequestId::class);
            $kernel->pushMiddleware(SecurityHeaders::class);
        }

        RateLimiter::for('api', function (Request $request) {
            $limit = Limit::perMinute((int) config('eduksx.rate_limit.api', 120));
            $token = $request->bearerToken();

            return $token !== null ? $limit->by('token:'.hash('sha256', $token)) : $limit->by('ip:'.$request->ip());
        });
    }
}
