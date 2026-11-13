<?php

namespace EduKSX\Core\Testing;

use EduKSX\Core\Module;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;

/**
 * Konformitätstest der EduKSX-Plattform (DB-Standard E5, API-Standard D8) für die CI jedes Moduls.
 *
 * Im Modul (tests/Feature/ConformanceTest.php):
 *
 *     final class ConformanceTest extends TestCase
 *     {
 *         use RefreshDatabase, \EduKSX\Core\Testing\AssertsConformance;
 *
 *         public function test_database(): void { $this->assertDatabaseConforms(); }
 *         public function test_api_basics(): void { $this->assertApiBasicsConform(); }
 *         public function test_openapi(): void { $this->assertOpenApiConforms(base_path('openapi/glossary-v1.json')); }
 *     }
 *
 * Core 0.1 prüft: Namen, STRICT (SQLite), Index auf Fremdschlüsseln; Fehlerformat, Request-ID,
 * Sicherheitskopfzeilen; Modulschlüssel und Abgleich Routen ↔ OpenAPI. Datenklassen und Zweck je Tabelle folgen mit
 * Core 0.2.
 */
trait AssertsConformance
{
    /** Tabellen von Laravel selbst, die nicht dem Modul gehören. */
    protected function conformanceFrameworkTables(): array
    {
        return ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];
    }

    /**
     * DB-Standard A1, A4, E5: Tabellen- und Spaltennamen snake_case (ASCII), SQLite-Tabellen STRICT, jeder
     * Fremdschlüssel mit Index.
     *
     * @param  list<string>  $ignoreTables  weitere Tabellen ohne Prüfung (mit Eintrag im Ausnahmeregister)
     */
    public function assertDatabaseConforms(array $ignoreTables = []): void
    {
        $ignore = array_merge($this->conformanceFrameworkTables(), $ignoreTables);
        $driver = DB::getDriverName();
        $problems = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (in_array($name, $ignore, true) || str_starts_with($name, 'sqlite_')) {
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $name) !== 1) {
                $problems[] = "Tabelle «{$name}»: Name nicht in snake_case (A1).";
            }
            foreach (Schema::getColumnListing($name) as $column) {
                if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $column) !== 1) {
                    $problems[] = "Spalte «{$name}.{$column}»: Name nicht in snake_case (A1).";
                }
            }
            if ($driver === 'sqlite') {
                $sql = (string) DB::scalar("select sql from sqlite_master where type = 'table' and name = ?", [$name]);
                if (preg_match('/\)\s*strict\s*$/i', $sql) !== 1) {
                    $problems[] = "Tabelle «{$name}»: nicht STRICT (A4, StrictSchema::harden).";
                }
            }
            $indexes = Schema::getIndexes($name);
            foreach (Schema::getForeignKeys($name) as $foreign) {
                $columns = implode(',', $foreign['columns']);
                if (! $this->conformanceForeignKeyIndexed($foreign['columns'], $indexes)) {
                    $problems[] = "Tabelle «{$name}»: Fremdschlüssel ({$columns}) ohne Index (E5).";
                }
            }
        }

        $this->assertSame([], $problems, "DB-Konformität:\n- ".implode("\n- ", $problems));
    }

    /**
     * API-Standard A6, C6, C7: unbekannter Endpunkt liefert problem+json mit den Pflichtfeldern, Request-ID und
     * Sicherheitskopfzeilen.
     */
    public function assertApiBasicsConform(string $unknownPath = '/api/v1/__eduksx_conformance__'): void
    {
        $response = $this->getJson($unknownPath);

        $response->assertStatus(404);
        $this->assertStringStartsWith('application/problem+json', (string) $response->headers->get('Content-Type'), 'Fehler nicht als application/problem+json (A6).');
        foreach (['type', 'title', 'status', 'code', 'request_id'] as $field) {
            $this->assertNotNull($response->json($field), "Fehlerfeld «{$field}» fehlt (A6).");
        }
        $this->assertSame($response->json('request_id'), $response->headers->get('X-Request-Id'), 'X-Request-Id fehlt oder weicht ab (C7).');
        foreach (['X-Content-Type-Options', 'X-Frame-Options', 'Content-Security-Policy', 'Referrer-Policy'] as $header) {
            $this->assertTrue($response->headers->has($header), "Sicherheitskopfzeile «{$header}» fehlt (C6).");
        }
    }

    /**
     * API-Standard D3: Modulschlüssel in info.x-eduksx-module, Version des Standards angegeben, jede Route unter
     * /api/v<n> steht in der Beschreibung und umgekehrt (Pfade relativ zur ersten servers-URL), operationId eindeutig.
     *
     * @param  list<string>  $ignoreRoutes  z. B. ['GET /api/v1/openapi.json']
     */
    public function assertOpenApiConforms(string $specPath, array $ignoreRoutes = []): void
    {
        $this->assertFileExists($specPath, 'OpenAPI-Beschreibung fehlt (D3, Vertrag zuerst).');
        /** @var array<string, mixed> $spec */
        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertMatchesRegularExpression('/^3\.1\./', (string) ($spec['openapi'] ?? ''), 'OpenAPI 3.1 erwartet (D3).');
        $this->assertSame(Module::key(), $spec['info']['x-eduksx-module'] ?? null, 'info.x-eduksx-module passt nicht zum Modulschlüssel (D3, G2).');
        $this->assertNotEmpty($spec['info']['x-eduksx-api-standard'] ?? null, 'info.x-eduksx-api-standard fehlt (D3).');

        // Präfix aus der ersten servers-URL (z. B. https://glossary.eduksx.ch/api + /v1/terms)
        $prefix = rtrim((string) parse_url((string) ($spec['servers'][0]['url'] ?? ''), PHP_URL_PATH), '/');

        $documented = [];
        $operationIds = [];
        foreach ((array) ($spec['paths'] ?? []) as $path => $operations) {
            $path = $prefix.'/'.ltrim((string) $path, '/');
            foreach ((array) $operations as $method => $operation) {
                if (! in_array($method, ['get', 'put', 'post', 'patch', 'delete'], true)) {
                    continue;
                }
                $documented[] = strtoupper($method).' '.$this->normalizeApiPath((string) $path);
                $operationIds[] = (string) ($operation['operationId'] ?? '');
            }
        }

        $routes = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $uri = '/'.ltrim($route->uri(), '/');
            if (preg_match('#^/api/v[0-9]+(/|$)#', $uri) !== 1) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $key = $method.' '.$this->normalizeApiPath($uri);
                if (! in_array($key, $ignoreRoutes, true)) {
                    $routes[] = $key;
                }
            }
        }

        $missingInSpec = array_values(array_diff(array_unique($routes), $documented));
        $missingInCode = array_values(array_diff($documented, $routes, $ignoreRoutes));
        sort($missingInSpec);
        sort($missingInCode);
        $this->assertSame([], $missingInSpec, "Routen ohne Beschreibung (D3):\n- ".implode("\n- ", $missingInSpec));
        $this->assertSame([], $missingInCode, "Beschriebene Operationen ohne Route (D3):\n- ".implode("\n- ", $missingInCode));
        $this->assertNotContains('', $operationIds, 'operationId fehlt (D3).');
        $this->assertSame(count($operationIds), count(array_unique($operationIds)), 'operationId nicht eindeutig (D3).');
    }

    /**
     * Ein Fremdschlüssel gilt als indiziert, wenn ein Index mit seinen Spalten beginnt oder ein eindeutiger Index
     * (auch der Primärschlüssel) aus seinen ersten Spalten besteht – z. B. (id, published_revision_id) über den
     * Primärschlüssel id.
     *
     * @param  list<string>  $columns
     * @param  list<array{columns: list<string>, unique: bool, primary: bool}>  $indexes
     */
    private function conformanceForeignKeyIndexed(array $columns, array $indexes): bool
    {
        foreach ($indexes as $index) {
            $cols = $index['columns'];
            if (array_slice($cols, 0, count($columns)) === $columns) {
                return true;
            }
            if (($index['unique'] || $index['primary']) && array_slice($columns, 0, count($cols)) === $cols) {
                return true;
            }
        }

        return false;
    }

    /** Pfadparameter vereinheitlichen: /terms/{term} und /terms/{term_id} gelten als gleich. */
    private function normalizeApiPath(string $path): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{}', rtrim($path, '/'));
    }
}
