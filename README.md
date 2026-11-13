# EduKSX-Core

Gemeinsame PHP-Bibliothek eduksx/core für alle Module der EduKSX-Plattform: Bausteine für Fehler, Sicherheitskopfzeilen, Abruflimits, Datenbank-Helfer, Pagination, ETag sowie Konformitätstests nach DB- und API-Standard – für Entwickler:innen der Module.

## Einsatz in einem Modul

```bash
composer require eduksx/core:^0.2
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
| Steckbrief `/.well-known/eduksx-module.json` | `Platform\Manifest` (aus `eduksx.manifest`) | Plattform S5 |
| Bereitschaft `/health` (200/503), eigene Prüfungen | `Platform\Health`, `Platform\HealthResult` | Plattform S5, API D6 |
| Empfang von S4-Ereignissen mit Signatur und Idempotenz | `Events\ReceivePlatformEvent`, `Events\PlatformEventReceived` | Plattform S4, SP6 |
| Signieren und Prüfen `EduKSX-Signature` (Absender und Empfänger) | `Events\Signer`, `Events\CloudEvent` | SP6 |
| Historie `data_changes` mit Datenklassen | `Data\RecordsDataChanges`, `Data\DataChanges`, `Data\DataClasses` | DB A7, B2 |
| Plattform-Schemas aus `spec/` und Prüfung dagegen | `Spec\Schemas`, `resources/spec/` | Core C11 |

### Steckbrief und /health (S5)

Mit gesetztem `EDUKSX_MODULE` antworten `GET /.well-known/eduksx-module.json` und `GET /health`. Die Werte des
Steckbriefs stehen in `config/eduksx.php` → `manifest` (Rollen, Scopes, abonnierte Ereignisse …); die Version kommt aus
`EDUKSX_VERSION` bzw. der Datei `VERSION`. `/health` prüft `database` und `migrations` (bei Fehler 503) sowie
`queue` und `storage` (bei Fehler `degraded`). Eigene Prüfung, nur mit Namen aus dem Schema:

```php
Health::register('ident', fn () => $ok ? HealthResult::ok() : HealthResult::fail('Ident nicht erreichbar.'));
```

### S4-Ereignisse empfangen

```bash
php artisan vendor:publish --tag=eduksx-events-migrations   # Tabelle received_events (Idempotenz)
```

```dotenv
EDUKSX_EVENTS_KEYS=<Schlüssel von Ident, ≥ 32 Zeichen>[,<neuer Schlüssel beim Wechsel>]
EDUKSX_EVENTS_SOURCES=https://ident.eduksx.ch     # optional: nur diese Absender
```

Danach nimmt `POST /api/v1/platform-events` signierte CloudEvents an (ohne Token; 401 bei falscher, fehlender oder
über 300 s alter Signatur, 422 bei Verstoss gegen `event.schema.json`, 204 sonst – auch bei Wiederholung). Die Arten,
die Ident zustellen soll, stehen im Steckbrief unter `events_consumed`. Verarbeiten:

```php
Event::listen('eduksx:ident.user.deleted', function (PlatformEventReceived $e) {
    // $e->data['user_id'] … – wirft der Listener, gilt das Ereignis als nicht verarbeitet (500, Ident wiederholt)
});
```

Bereits verarbeitete Ereignis-IDs räumt Core täglich auf (`eduksx.events.retention_days`, Standard 30). Tests:
Trait `Testing\SendsPlatformEvents` (`$this->postPlatformEvent('ident.user.deleted', [...])`). Absender (Ident)
nutzen `CloudEvent::make()`, `CloudEvent::encode()` und `Signer::header()`.

### Historie data_changes

```bash
php artisan vendor:publish --tag=eduksx-history-migrations   # besteht die Tabelle schon, bleibt sie unverändert
```

```php
DataClasses::define(['notes' => ['id' => DataClass::Internal, 'title' => DataClass::Public, 'author_email' => DataClass::Personal]]);
DataChanges::resolveActorUsing(fn () => new Actor('editor', auth()->id()));   // optional

class Note extends Model { use \EduKSX\Core\Data\RecordsDataChanges; }
```

Jede Änderung landet mit Feldern, `request_id`, Transaktion und handelnder Person in `data_changes`; Werte der Klassen
personenbezogen, besonders schützenswert und geheim nur als `changed`. Quelle für Importe:
`DataChanges::withSource('import', fn () => …)`; Kontolöschung: `DataChanges::forget($personId)`.

### Plattform-Schemas

`resources/spec/v1/` enthält die Schemas aus `spec/` des Repos EduKSX-Architektur unverändert (Herkunft und
Prüfsummen in `resources/spec/SOURCE.json`). Prüfen: `Schemas::errors('event', $daten)` bzw. `Schemas::assertValid()`.
Leere JSON-Objekte als `(object) []` übergeben. Aktualisieren nur mit
`php bin/spec.php update <Pfad zum Architektur-Repo> [<Referenz>]`; die CI prüft mit `php bin/spec.php check`.

### Konformitätstest im Modul

```php
final class ConformanceTest extends TestCase
{
    use RefreshDatabase, \EduKSX\Core\Testing\AssertsConformance;

    public function test_database(): void { $this->assertDatabaseConforms(); }
    public function test_api_basics(): void { $this->assertApiBasicsConform(); }
    public function test_openapi(): void { $this->assertOpenApiConforms(base_path('openapi/glossary-v1.json')); }
    public function test_platform(): void { $this->assertPlatformEndpointsConform(); }   // Steckbrief, /health
    public function test_data_classes(): void { $this->assertDataClassesComplete(); }    // jede Spalte mit Datenklasse
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
