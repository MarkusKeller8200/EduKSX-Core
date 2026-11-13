<?php

namespace EduKSX\Core\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ETags nach API-Standard B2 und B5 – zwei getrennte Arten:
 *
 * - **Objekt-ETag** (B2) für änderbare Objekte: stark, `"<id>.<lock_version>"`, steht für den Stand des Objekts.
 *   `PATCH`/`PUT`/`DELETE` verlangen `If-Match` (fehlt: 428, passt nicht: 412 mit aktuellem ETag).
 * - **Inhalts-ETag** (B5) für öffentliche Lese-Endpunkte: Prüfsumme des Inhalts, `If-None-Match` → 304,
 *   `Cache-Control: public, max-age=60`, `Vary: Accept-Language`.
 *
 * Antworten mit Organisations- oder Personendaten bekommen keinen öffentlichen ETag, sondern privateHeaders().
 */
final class ETag
{
    public static function forModel(Model $model): string
    {
        return '"'.$model->getKey().'.'.(int) $model->getAttribute('lock_version').'"';
    }

    /** Vor PATCH/PUT/DELETE: If-Match muss den aktuellen Stand nennen. */
    public static function requireMatch(Request $request, Model $model): void
    {
        $current = self::forModel($model);
        $header = $request->headers->get('If-Match');
        if ($header === null || trim($header) === '') {
            throw ProblemException::common(428, 'precondition_required', 'If-Match mit dem ETag des Objekts fehlt.', ['ETag' => $current]);
        }
        $given = array_map('trim', explode(',', $header));
        if (! in_array('*', $given, true) && ! in_array($current, $given, true)) {
            throw ProblemException::common(412, 'precondition_failed', 'Das Objekt wurde inzwischen geändert.', ['ETag' => $current]);
        }
    }

    /** Antwort mit dem Objekt-ETag versehen (nach Lesen oder Ändern). */
    public static function withModel(JsonResponse $response, Model $model): JsonResponse
    {
        $response->headers->set('ETag', self::forModel($model));

        return $response;
    }

    /**
     * Öffentliche Leseantwort mit Inhalts-ETag; 304, wenn der Abnehmer diesen Stand schon hat.
     *
     * @param  array<string, string>  $headers  z. B. ['EduKSX-Release' => '42']
     */
    public static function publicJson(Request $request, mixed $data, int $maxAge = 60, array $headers = []): JsonResponse|Response
    {
        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $etag = '"'.hash('sha256', $json).'"';
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age='.$maxAge,
            'Vary' => 'Accept-Language',
        ] + $headers;

        $match = array_map('trim', explode(',', (string) $request->headers->get('If-None-Match', '')));
        if (in_array($etag, $match, true) || in_array('W/'.$etag, $match, true) || in_array('*', $match, true)) {
            return new Response('', 304, $headers);
        }

        return JsonResponse::fromJsonString($json, 200, $headers);
    }

    /** @return array<string, string> Kopfzeilen für Antworten mit Organisations- oder Personendaten. */
    public static function privateHeaders(): array
    {
        return ['Cache-Control' => 'private, no-store'];
    }
}
