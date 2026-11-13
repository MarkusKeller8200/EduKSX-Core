<?php

namespace EduKSX\Core;

use InvalidArgumentException;

/**
 * Modulschlüssel nach dem Modulregister (DB-Standard E1, API-Standard G2): englisch, nur Kleinbuchstaben a–z,
 * ohne Bindestrich. Aus ihm leiten sich die Präfixe für Fehlertypen, Tokens, Ereignisse und MCP-Werkzeuge ab.
 */
final class Module
{
    public static function key(): string
    {
        $key = config('eduksx.module');
        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('EDUKSX_MODULE ist nicht gesetzt (Modulschlüssel aus dem Modulregister).');
        }
        self::assertValid($key);

        return $key;
    }

    public static function assertValid(string $key): void
    {
        if (preg_match('/^[a-z]{2,20}$/', $key) !== 1) {
            throw new InvalidArgumentException("Ungültiger Modulschlüssel «{$key}»: nur Kleinbuchstaben a–z, 2–20 Zeichen.");
        }
    }

    /** Präfix für Tokens: `<modul>_<art>_` (API-Standard C1). */
    public static function tokenPrefix(string $kind): string
    {
        return self::key().'_'.$kind.'_';
    }

    /** Ereignisname `<modul>.<objekt>.<verb>` (API-Standard B7). */
    public static function event(string $object, string $verb): string
    {
        return self::key().'.'.$object.'.'.$verb;
    }
}
