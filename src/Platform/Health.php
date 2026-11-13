<?php

namespace EduKSX\Core\Platform;

use Closure;
use EduKSX\Core\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Bereitschaft des Moduls `GET /health` (Plattform S5, API D6, spec/v1/health.schema.json): HTTP 200 bei `ok` oder
 * `degraded`, 503 bei `fail`. Meldungen sind feste Texte – nie Ausnahmetexte, Hostnamen, Personendaten oder
 * Geheimnisse; Einzelheiten stehen im Protokoll.
 *
 * Eingebaute Prüfungen: database, migrations (kritisch), queue, storage. Eigene Prüfung im Modul, z. B. im
 * AppServiceProvider (Namen nur aus dem Schema: ident, deletion_register, oder eingebaute ersetzen):
 *
 *     Health::register('ident', fn () => HealthResult::ok());
 */
final class Health
{
    /** Erlaubte Namen laut spec/v1/health.schema.json. */
    public const NAMES = ['database', 'migrations', 'queue', 'storage', 'ident', 'deletion_register'];

    /** @var array<string, Closure(): HealthResult> */
    private static array $custom = [];

    /** @param  Closure(): HealthResult  $check */
    public static function register(string $name, Closure $check): void
    {
        if (! in_array($name, self::NAMES, true)) {
            throw new InvalidArgumentException("Prüfung «{$name}» ist im Schema nicht vorgesehen. Erlaubt: ".implode(', ', self::NAMES));
        }
        self::$custom[$name] = $check;
    }

    public static function forget(): void
    {
        self::$custom = [];
    }

    /** @return array{status: string, module: string, version: string, checked_at: string, checks: array<string, array<string, mixed>>} */
    public static function report(): array
    {
        $checks = [];
        $names = array_values(array_unique(array_merge((array) config('eduksx.health.checks', []), array_keys(self::$custom))));
        foreach ($names as $name) {
            $started = hrtime(true);
            try {
                $result = isset(self::$custom[$name]) ? (self::$custom[$name])() : self::builtIn($name);
            } catch (Throwable $e) {
                Log::warning('eduksx.health.check_failed', ['check' => $name, 'exception' => $e::class]);
                $result = HealthResult::fail('Prüfung fehlgeschlagen.');
            }
            $checks[$name] = $result->toArray((int) ((hrtime(true) - $started) / 1_000_000));
        }

        $critical = (array) config('eduksx.health.critical', ['database', 'migrations']);
        $status = 'ok';
        foreach ($checks as $name => $check) {
            if ($check['status'] === 'fail' && in_array($name, $critical, true)) {
                $status = 'fail';
                break;
            }
            if ($check['status'] !== 'ok') {
                $status = 'degraded';
            }
        }

        return [
            'status' => $status,
            'module' => Module::key(),
            'version' => Module::version(),
            'checked_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'checks' => $checks,
        ];
    }

    public static function response(): JsonResponse
    {
        $report = self::report();

        return new JsonResponse($report, $report['status'] === 'fail' ? 503 : 200, [
            'Cache-Control' => 'no-store',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function builtIn(string $name): HealthResult
    {
        return match ($name) {
            'database' => self::database(),
            'migrations' => self::migrations(),
            'queue' => self::queue(),
            'storage' => self::storage(),
            default => throw new InvalidArgumentException("Keine eingebaute Prüfung «{$name}»."),
        };
    }

    private static function database(): HealthResult
    {
        DB::connection()->select('select 1');

        return HealthResult::ok();
    }

    /** Ausstehende Migrationen heissen: Code und Datenbank passen nicht zusammen. */
    private static function migrations(): HealthResult
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return HealthResult::fail('Migrationen noch nie ausgeführt.');
        }
        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

        return $pending === [] ? HealthResult::ok() : HealthResult::fail(count($pending).' Migration(en) ausstehend.');
    }

    private static function queue(): HealthResult
    {
        if (config('queue.default') === 'sync') {
            return HealthResult::ok('Warteschlange synchron.');
        }
        Queue::connection()->size();

        return HealthResult::ok();
    }

    private static function storage(): HealthResult
    {
        $disk = Storage::disk();
        $file = '.eduksx-health-'.bin2hex(random_bytes(4));
        $disk->put($file, 'ok');
        $ok = $disk->get($file) === 'ok';
        $disk->delete($file);

        return $ok ? HealthResult::ok() : HealthResult::fail('Speicher nicht beschreibbar.');
    }
}
