<?php

namespace EduKSX\Core\Logging;

use Illuminate\Support\Facades\Context;

/**
 * W3C Trace Context (KONZEPT-PROTOKOLLIERUNG §7): `traceparent` = 00-<trace-id 32 hex>-<parent-id 16 hex>-<flags>.
 * Die trace_id bleibt über Module, Ereignisse und Aufträge gleich; jeder Schritt bekommt eine eigene span_id.
 * Gespeichert im Laravel-Context – damit steht sie in jedem Protokolleintrag und geht in Aufträge der Warteschlange mit.
 */
final class TraceContext
{
    public const HEADER = 'traceparent';

    private const PATTERN = '/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/';

    /** Eingehenden traceparent übernehmen (gültig, nicht nur Nullen) oder neuen Trace beginnen; gibt den eigenen zurück. */
    public static function start(?string $incoming = null): string
    {
        $traceId = null;
        $flags = '01';
        if ($incoming !== null && preg_match(self::PATTERN, strtolower(trim($incoming)), $m) === 1
            && $m[1] !== str_repeat('0', 32) && $m[2] !== str_repeat('0', 16)) {
            $traceId = $m[1];
            $flags = $m[3];
        }
        $traceId ??= bin2hex(random_bytes(16));
        $spanId = bin2hex(random_bytes(8));
        $traceparent = "00-{$traceId}-{$spanId}-{$flags}";

        Context::add('trace_id', $traceId);
        Context::addHidden(self::HEADER, $traceparent);

        return $traceparent;
    }

    public static function traceId(): ?string
    {
        $id = Context::get('trace_id');

        return is_string($id) ? $id : null;
    }

    /** traceparent für einen Aufruf an ein anderes Modul (S2): gleiche trace_id, neue span_id. */
    public static function child(): string
    {
        $current = Context::getHidden(self::HEADER);
        if (! is_string($current) || preg_match(self::PATTERN, $current, $m) !== 1) {
            $current = self::start();
            preg_match(self::PATTERN, $current, $m);
        }

        return "00-{$m[1]}-".bin2hex(random_bytes(8))."-{$m[3]}";
    }

    /**
     * Kopfzeilen für Dienst-zu-Dienst-Aufrufe (S2): Http::withHeaders(TraceContext::headers())->get(…).
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $headers = [self::HEADER => self::child()];
        $requestId = Context::get('request_id');
        if (is_string($requestId)) {
            $headers['X-Request-Id'] = $requestId;
        }

        return $headers;
    }
}
