<?php

namespace EduKSX\Core;

use EduKSX\Core\Auth\LocalLogin;
use EduKSX\Core\Console\DebugCommand;
use EduKSX\Core\Console\LogPackageCommand;
use EduKSX\Core\Console\RolesMatrixCommand;
use EduKSX\Core\Console\SecurityLogCommand;
use EduKSX\Core\Events\ReceivedEvent;
use EduKSX\Core\Events\ReceivePlatformEvent;
use EduKSX\Core\Http\Middleware\AssignRequestId;
use EduKSX\Core\Http\Middleware\SecurityHeaders;
use EduKSX\Core\Logging\OrganizationDebug;
use EduKSX\Core\Platform\Health;
use EduKSX\Core\Platform\Manifest;
use EduKSX\Core\Settings\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Bindet eduksx/core in ein Modul ein (Package Discovery): Konfiguration, Request-ID und Sicherheitskopfzeilen für
 * alle Anfragen, Abruflimit «api» (API-Standard C5); ab 0.2 Steckbrief und /health (S5) sowie Empfang von
 * S4-Ereignissen. Fehler als problem+json bindet das Modul in bootstrap/app.php ein:
 * ->withExceptions(fn ($e) => \EduKSX\Core\Http\Problem::register($e)).
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eduksx.php', 'eduksx');
        // je Anfrage frisch (Octane, Warteschlange): gespeicherte Werte werden pro Instanz zwischengespeichert
        $this->app->scoped(Settings::class, function (): Settings {
            $settings = new Settings((string) config('eduksx.settings.table', 'settings'));
            $settings->define(OrganizationDebug::SETTING, OrganizationDebug::definition());
            if (config('eduksx.auth.local', false)) {
                LocalLogin::register($settings);
            }

            return $settings;
        });
    }

    /**
     * CORS-Voreinstellung (API-Standard C6), solange das Modul die offene Laravel-Vorgabe (alle Methoden) nicht
     * durch eine eigene config/cors.php ersetzt hat.
     */
    private function applyCorsDefaults(): void
    {
        $framework = dirname((string) (new \ReflectionClass(Application::class))->getFileName(), 4).'/config/cors.php';
        $laravelDefault = is_file($framework) ? require $framework : null;
        if ($laravelDefault === null || config('cors') === $laravelDefault) {
            config(['cors' => config('eduksx.cors')]);
        }
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/eduksx.php' => config_path('eduksx.php')], 'eduksx-config');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'eduksx');
        $this->publishesMigrations([__DIR__.'/../database/migrations/events' => database_path('migrations')], 'eduksx-events-migrations');
        $this->publishesMigrations([__DIR__.'/../database/migrations/history' => database_path('migrations')], 'eduksx-history-migrations');
        $this->publishesMigrations([__DIR__.'/../database/migrations/settings' => database_path('migrations')], 'eduksx-settings-migrations');
        $this->publishesMigrations([__DIR__.'/../database/migrations/security' => database_path('migrations')], 'eduksx-security-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([DebugCommand::class, LogPackageCommand::class, RolesMatrixCommand::class, SecurityLogCommand::class]);
        }
        if (config('eduksx.schedule', true)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('eduksx:debug', ['--expire' => true])->everyMinute()->name('eduksx-debug-expire')->withoutOverlapping();
                $schedule->command('eduksx:sicherheitsprotokoll', ['--prune' => true])->dailyAt('02:10')->name('eduksx-security-checkpoint');
                $schedule->command('eduksx:protokollpaket', ['--prune' => true])->dailyAt('02:20')->name('eduksx-log-package-prune');
            });
        }

        $this->applyCorsDefaults();

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
        RateLimiter::for('eduksx-events', fn (Request $request) => Limit::perMinute((int) config('eduksx.events.rate_limit', 600))->by('ip:'.$request->ip()));

        $this->registerRoutes();
    }

    /** Steckbrief und /health (S5) mit Modulschlüssel; Empfang S4, sobald ein Schlüssel gesetzt ist. */
    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }
        $module = config('eduksx.module');
        if (is_string($module) && $module !== '') {
            if (config('eduksx.routes.manifest', true)) {
                Route::get('/.well-known/eduksx-module.json', fn (Request $request) => Manifest::response($request))->name('eduksx.manifest');
            }
            if (config('eduksx.routes.health', true)) {
                Route::get('/health', fn () => Health::response())->name('eduksx.health');
            }
        }
        if ((array) config('eduksx.events.keys', []) !== []) {
            Route::post((string) config('eduksx.events.path', 'api/v1/platform-events'), ReceivePlatformEvent::class)
                ->middleware('throttle:eduksx-events')->name('eduksx.events');
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('model:prune', ['--model' => [ReceivedEvent::class]])->daily()->name('eduksx-events-prune');
            });
        }
    }
}
