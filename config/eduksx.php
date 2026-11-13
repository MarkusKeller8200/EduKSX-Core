<?php

return [
    /*
    | Modulschlüssel aus dem Modulregister (EduKSX-Architektur docs/REGISTER-MODULE.md): englisch, Kleinbuchstaben,
    | ohne Bindestrich, nie geändert. Benennt Fehlertypen, Tokens, Ereignisse und MCP-Werkzeuge (API-Standard G2).
    */
    'module' => env('EDUKSX_MODULE'),

    /*
    | Version des Moduls (SemVer) für Steckbrief, /health und Historie. Leer: Datei VERSION im Modul.
    */
    'version' => env('EDUKSX_VERSION'),

    /*
    | Steckbrief GET /.well-known/eduksx-module.json (Plattform S5, spec/v1/module-manifest.schema.json).
    | Öffentlich: keine Geheimnisse, Adressen relativ (z. B. /docs/api). `standards` leer = die von Core.
    */
    'manifest' => [
        'name' => env('EDUKSX_MODULE_NAME'),
        'operation_mode' => 'central',          // central | isolated | internal
        'api_versions' => ['v1'],
        'scopes_offered' => [],                 // z. B. 'glossary:terms.read'
        'scopes_required' => [],
        'roles' => [],                          // Rollen für den Claim eduksx_roles, z. B. 'reader', 'editor'
        'events_emitted' => [],
        'events_consumed' => [],                // z. B. 'ident.user.deleted' – Ident stellt nur diese Arten zu
        'classification' => null,               // public | internal | confidential | highly_sensitive
        'data_license' => null,
        'urls' => [
            // 'up' => '/up', 'health' => '/health' (Vorgabe), 'openapi' => '/openapi/v1.json', 'docs' => …,
            // 'metrics' => …, 'security_contact' => 'mailto:…'
        ],
        'ui' => null,                           // Plugin-Beschreibung (KONZEPT-PLUGINS), sonst null
    ],

    /*
    | Bereitschaft GET /health (S5, API D6). Kritische Prüfungen führen bei «fail» zu 503, die übrigen zu «degraded».
    */
    'health' => [
        'checks' => ['database', 'migrations', 'queue', 'storage'],
        'critical' => ['database', 'migrations'],
    ],

    /*
    | Routen von Core. Steckbrief und /health nur, wenn ein Modulschlüssel gesetzt ist.
    */
    'routes' => [
        'manifest' => true,
        'health' => true,
    ],

    /*
    | Empfang von S4-Ereignissen (Plattform S4, SP6). Aktiv, sobald ein Schlüssel gesetzt ist. Mehrere Schlüssel
    | durch Komma getrennt (Schlüsselwechsel). Tabelle: php artisan vendor:publish --tag=eduksx-events-migrations
    */
    'events' => [
        'path' => 'api/v1/platform-events',
        'keys' => array_values(array_filter(explode(',', (string) env('EDUKSX_EVENTS_KEYS', '')))),
        'sources' => array_values(array_filter(explode(',', (string) env('EDUKSX_EVENTS_SOURCES', '')))),   // leer = alle
        'tolerance' => 300,                     // Sekunden
        'max_bytes' => 262144,
        'rate_limit' => 600,                    // je Minute und Absender
        'retention_days' => 30,                 // Ereignis-IDs für die Idempotenz
    ],

    /*
    | Historie data_changes (DB-Standard A7, B2). Tabelle: php artisan vendor:publish --tag=eduksx-history-migrations
    */
    'history' => [
        'table' => 'data_changes',
    ],

    /*
    | Basis der Fehlertypen (API-Standard A6). Gemeinsame Typen: {base}/{code}, modul-eigene: {base}/{module}/{code}.
    */
    'problem_base_url' => env('EDUKSX_PROBLEM_BASE_URL', 'https://docs.eduksx.ch/problems'),

    /*
    | Pfade, deren Fehler als application/problem+json kommen (Request::is-Muster).
    */
    'api_paths' => ['api/*'],

    /*
    | Abruflimit «api» je Minute (API-Standard C5): je Token, sonst je Adresse.
    */
    'rate_limit' => [
        'api' => (int) env('EDUKSX_RATE_LIMIT_API', 120),
    ],

    /*
    | Sicherheitskopfzeilen (API-Standard C6, Plattform §6). frame-src ergänzen nur, wenn das Modul etwas einbettet.
    */
    'security_headers' => [
        'frame_src' => ["'self'"],
        'hsts' => 'max-age=31536000; includeSubDomains',
    ],

    /*
    | CORS-Voreinstellung (API-Standard C6), gilt, solange das Modul keine eigene config/cors.php hat:
    | öffentliche GET-Endpunkte für alle Herkünfte, ohne Cookies. Schreibende Endpunkte: eigene config/cors.php mit
    | einer Liste von Herkünften je Instanz, nie «*» mit Anmeldedaten.
    */
    'cors' => [
        'paths' => ['api/*'],
        'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],
        'allowed_origins' => ['*'],
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['Accept', 'Accept-Language', 'Authorization', 'Content-Type', 'If-None-Match', 'X-Request-Id'],
        'exposed_headers' => ['ETag', 'EduKSX-Release', 'Retry-After', 'X-Request-Id'],
        'max_age' => 86400,
        'supports_credentials' => false,
    ],
];
