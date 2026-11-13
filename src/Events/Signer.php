<?php

namespace EduKSX\Core\Events;

/**
 * Signatur der S4-Ereignisse (Plattform S4, SP6, spec/v1/signature.schema.json):
 *
 *     EduKSX-Signature: t=<Unix-Sekunden>,v1=<hex>[,v1=<hex>…]
 *
 * v1 = HMAC-SHA256(Schlüssel, "<t>.<roher Body>"). Beim Schlüsselwechsel signiert der Absender mit beiden Schlüsseln
 * (mehrere v1=), der Empfänger akzeptiert jeden seiner Schlüssel. Abweisung nach 300 s, Vergleich in konstanter Zeit.
 * Dieselbe Klasse dient dem Absender (Ident) und dem Empfänger (Module).
 */
final class Signer
{
    public const HEADER = 'EduKSX-Signature';

    public const TOLERANCE = 300;

    /**
     * Kopfzeile für einen Body (Absender).
     *
     * @param  string|list<string>  $keys  ein Schlüssel oder mehrere beim Wechsel
     */
    public static function header(string $body, string|array $keys, ?int $time = null): string
    {
        $time ??= time();
        $parts = ['t='.$time];
        foreach ((array) $keys as $key) {
            self::assertKey($key);
            $parts[] = 'v1='.hash_hmac('sha256', $time.'.'.$body, $key);
        }

        return implode(',', $parts);
    }

    /**
     * Prüft die Kopfzeile (Empfänger). Wirft InvalidSignature mit Grund: missing, malformed, expired, mismatch.
     *
     * @param  list<string>  $keys  gültige Schlüssel des Empfängers
     */
    public static function verify(?string $header, string $body, array $keys, int $tolerance = self::TOLERANCE, ?int $now = null): void
    {
        if ($header === null || trim($header) === '') {
            throw new InvalidSignature('missing');
        }
        if ($keys === []) {
            throw new InvalidSignature('no_key');
        }
        $time = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($name === 't' && preg_match('/^\d{1,12}$/', $value) === 1) {
                $time = (int) $value;
            } elseif ($name === 'v1' && preg_match('/^[0-9a-f]{64}$/', $value) === 1) {
                $signatures[] = $value;
            }
            // unbekannte Schemata (z. B. v2) werden ignoriert
        }
        if ($time === null || $signatures === []) {
            throw new InvalidSignature('malformed');
        }
        $now ??= time();
        if (abs($now - $time) > $tolerance) {
            throw new InvalidSignature('expired');
        }
        foreach ($keys as $key) {
            $expected = hash_hmac('sha256', $time.'.'.$body, $key);
            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return;
                }
            }
        }
        throw new InvalidSignature('mismatch');
    }

    private static function assertKey(string $key): void
    {
        if (strlen($key) < 32) {
            throw new \InvalidArgumentException('Signaturschlüssel zu kurz (mindestens 32 Zeichen).');
        }
    }
}
