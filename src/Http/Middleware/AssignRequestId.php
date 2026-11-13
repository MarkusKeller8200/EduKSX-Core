<?php

namespace EduKSX\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jede Anfrage bekommt eine request_id (API-Standard C7): eingehendes `X-Request-Id` übernehmen, wenn es ein
 * sicheres Format hat, sonst eine ULID erzeugen. Steht danach im Request-Attribut, im Protokoll-Kontext und in der
 * Antwort-Kopfzeile.
 */
final class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const ATTRIBUTE = 'eduksx.request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = self::forRequest($request);
        Log::withContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    /** request_id der Anfrage; legt sie bei Bedarf an (auch für Fehler, die vor der Middleware entstehen). */
    public static function forRequest(Request $request): string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);
        if (is_string($id)) {
            return $id;
        }
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $id = preg_match('/^[A-Za-z0-9._-]{8,128}$/', $incoming) === 1 ? $incoming : (string) Str::ulid();
        $request->attributes->set(self::ATTRIBUTE, $id);

        return $id;
    }
}
