# EduKSX-Core

Gemeinsame PHP-Bibliothek eduksx/core für alle Module der EduKSX-Plattform: Bausteine für Fehler, Sicherheitskopfzeilen, Abruflimits, Datenbank-Helfer, Pagination, ETag sowie Konformitätstests nach DB- und API-Standard – für Entwickler:innen der Module.

## Einsatz in einem Modul

```bash
composer require eduksx/core:^0.1
```

`.env` des Moduls – Schlüssel aus dem [Modulregister](https://github.com/MarkusKeller8200/EduKSX-Architektur/blob/main/docs/REGISTER-MODULE.md):

```dotenv
EDUKSX_MODULE=glossary
```

Fehler als `application/problem+json` in `bootstrap/app.php`:

```php
->withExceptions(fn (Exceptions $exceptions) => \EduKSX\Core\Http\Problem::register($exceptions))
```

Der Service-Provider wird automatisch geladen und setzt für alle Anfragen eine `X-Request-Id` und die
Sicherheitskopfzeilen und – solange das Modul keine eigene `config/cors.php` hat – die CORS-Voreinstellung; das Abruflimit heisst `api` (`throttle:api`, Standard 120/Min.). Konfiguration anpassen:
`php artisan vendor:publish --tag=eduksx-config`.

| Baustein | Klasse | Regel |
|---|---|---|
| Fehler nach RFC 9457, modul-eigene Codes | `Http\Problem`, `Http\ProblemException` | API A6 |
| Request-ID | `Http\Middleware\AssignRequestId` | API C7 |
| Sicherheitskopfzeilen | `Http\Middleware\SecurityHeaders` | API C6 |
| Abruflimit je Token bzw. Adresse | `RateLimiter` «api» | API C5 |
| STRICT-Tabellen und CHECK-Regeln | `Database\StrictSchema` | DB A4 |
| Optimistisches Sperren mit `lock_version` | `Eloquent\OptimisticLocking` (409 `conflict`) | DB A6, API B2 |
| Cursor-Pagination (`cursor`, `limit`, `sort`) | `Http\CursorPagination::paginate()` | API A5 |
| Objekt-ETag mit `If-Match` (428/412), Inhalts-ETag mit 304 | `Http\ETag` | API B2, B5 |
| CORS-Voreinstellung: öffentliches Lesen ohne Cookies | `config('eduksx.cors')` | API C6 |
| Gemeinsame OpenAPI-Schemas (`Problem`, `CursorMeta`, Parameter) | `resources/openapi/components.json` | API D3 |
| Spectral-Regelsatz `eduksx` (vorerst Warnungen) | `resources/spectral/eduksx.yaml` | API D3 |
| Konformitätstest für die CI des Moduls | `Testing\AssertsConformance` | DB E5, API D8 |

### Konformitätstest im Modul

```php
final class ConformanceTest extends TestCase
{
    use RefreshDatabase, \EduKSX\Core\Testing\AssertsConformance;

    public function test_database(): void { $this->assertDatabaseConforms(); }
    public function test_api_basics(): void { $this->assertApiBasicsConform(); }
    public function test_openapi(): void { $this->assertOpenApiConforms(base_path('openapi/glossary-v1.json')); }
}
```

Linter in der CI des Moduls:

```bash
npx --yes @stoplight/spectral-cli@6.17.1 lint openapi/glossary-v1.json --ruleset vendor/eduksx/core/resources/spectral/eduksx.yaml
```

Umfang und Plan: [KONZEPT-CORE.md](https://github.com/MarkusKeller8200/EduKSX-Architektur/blob/main/docs/KONZEPT-CORE.md)
im Repo EduKSX-Architektur. Core setzt DB-Standard 1.2.1 und API-Standard 1.1.1 um (`composer.json` → `extra.eduksx`).

## Entwicklung

Voraussetzungen: PHP 8.4, Composer. `composer install`, dann `composer ci:check` (Format, Tests mit SQLite).
Mit PostgreSQL: `DB_CONNECTION=pgsql DB_DATABASE=core_test DB_USERNAME=core DB_PASSWORD=core composer test`.

## Mitmachen

Fehler, Verbesserungen und Ideen bitte über die Issue-Formulare melden. Wie Meldungen freigegeben und umgesetzt
werden, steht in [CONTRIBUTING.md](CONTRIBUTING.md) und [docs/GITHUB-ABLAUF.md](docs/GITHUB-ABLAUF.md).
Sicherheitslücken bitte nicht öffentlich melden, sondern wie in [SECURITY.md](SECURITY.md) beschrieben.

## Versionen

Änderungen stehen im [CHANGELOG.md](CHANGELOG.md). Releases entstehen nach Freigabe automatisch (`VERSION`,
Workflow `release.yml`).
