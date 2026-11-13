<?php

namespace EduKSX\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schutz-Kopfzeilen für alle Antworten (API-Standard C6, Plattform §6). Herkunft: Glossary bzw. sek-intranet.
 *
 * - Nur das Modul selbst darf sich einbetten (X-Frame-Options, CSP frame-ancestors) – gegen Clickjacking.
 * - Keine Plugins/Objekte, kein fremdes base, kein MIME-Raten, gekürzter Referrer, Geräte-Rechte aus, HSTS bei HTTPS.
 * - Was ein Modul einbettet, ergänzt es über config('eduksx.security_headers.frame_src').
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        /** @var list<string> $frames */
        $frames = (array) config('eduksx.security_headers.frame_src', ["'self'"]);
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Content-Security-Policy', implode('; ', [
            "frame-ancestors 'self'",
            'frame-src '.implode(' ', $frames),
            "object-src 'none'",
            "base-uri 'self'",
        ]));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', (string) config('eduksx.security_headers.hsts'));
        }

        return $response;
    }
}
