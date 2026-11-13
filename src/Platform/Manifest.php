<?php

namespace EduKSX\Core\Platform;

use EduKSX\Core\Module;
use EduKSX\Core\Spec\Schemas;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Steckbrief des Moduls `GET /.well-known/eduksx-module.json` (Plattform S5, spec/v1/module-manifest.schema.json).
 * Werte aus config/eduksx.php (`module`, `version`, `manifest.*`); öffentlich, ohne Geheimnisse und ohne Hostnamen
 * (Adressen relativ zur Herkunft des Steckbriefs).
 */
final class Manifest
{
    /** @return array<string, mixed> */
    public static function build(): array
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('eduksx.manifest', []);
        $urls = array_filter(array_merge([
            'up' => '/up',
            'health' => '/health',
        ], (array) ($config['urls'] ?? [])), fn ($v) => $v !== null && $v !== '');

        $manifest = [
            'manifest_version' => '1',
            'module' => Module::key(),
            'name' => (string) ($config['name'] ?? Module::key()),
            'version' => Module::version(),
            'operation_mode' => $config['operation_mode'] ?? 'central',
            'api_versions' => array_values((array) ($config['api_versions'] ?? ['v1'])),
            'standards' => (array) ($config['standards'] ?? Module::STANDARDS),
            'scopes_offered' => array_values((array) ($config['scopes_offered'] ?? [])),
            'scopes_required' => array_values((array) ($config['scopes_required'] ?? [])),
            'roles' => array_values((array) ($config['roles'] ?? [])),
            'events_emitted' => array_values((array) ($config['events_emitted'] ?? [])),
            'events_consumed' => array_values((array) ($config['events_consumed'] ?? [])),
            'classification' => $config['classification'] ?? null,
            'urls' => $urls,
            'data_license' => $config['data_license'] ?? null,
            'ui' => $config['ui'] ?? null,
        ];

        return array_filter($manifest, fn ($v) => $v !== null);
    }

    /** @return list<string> Fehler gegen das Schema; leer, wenn gültig. */
    public static function errors(): array
    {
        return Schemas::errors('module-manifest', self::build());
    }

    public static function response(Request $request): SymfonyResponse
    {
        $json = (string) json_encode(self::build(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $etag = '"'.hash('sha256', $json).'"';
        $headers = ['Content-Type' => 'application/json', 'ETag' => $etag, 'Cache-Control' => 'public, max-age=300'];
        $match = array_map('trim', explode(',', (string) $request->headers->get('If-None-Match', '')));
        if (in_array($etag, $match, true) || in_array('W/'.$etag, $match, true)) {
            return new Response('', 304, $headers);
        }

        return new Response($json, 200, $headers);
    }
}
