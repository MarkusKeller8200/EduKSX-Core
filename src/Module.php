<?php

namespace EduKSX\Core;

use InvalidArgumentException;

/**
 * Modulschlüssel nach dem Modulregister (DB-Standard E1, API-Standard G2): englisch, nur Kleinbuchstaben a–z,
 * ohne Bindestrich. Aus ihm leiten sich die Präfixe für Fehlertypen, Tokens, Ereignisse und MCP-Werkzeuge ab.
 */
final class Module
{
    /** Versionen von DB- und API-Standard, die Core umsetzt (Core C9, auch in composer.json extra.eduksx). */
    public const STANDARDS = ['db' => '1.2.1', 'api' => '1.1.1'];

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
        // Teilmenge des Musters in spec/v1 (^[a-z][a-z0-9]{1,15}$): Register erlaubt nur Buchstaben
        if (preg_match('/^[a-z]{2,16}$/', $key) !== 1) {
            throw new InvalidArgumentException("Ungültiger Modulschlüssel «{$key}»: nur Kleinbuchstaben a–z, 2–16 Zeichen.");
        }
    }

    /**
     * Version des Moduls (SemVer) für Steckbrief, /health und Historie: EDUKSX_VERSION bzw. eduksx.version, sonst
     * die Datei VERSION im Modul (Blueprint), sonst 0.0.0.
     */
    public static function version(): string
    {
        $version = config('eduksx.version');
        if (! is_string($version) || $version === '') {
            $file = base_path('VERSION');
            $version = is_file($file) ? trim((string) file_get_contents($file)) : '';
        }

        return $version !== '' ? ltrim($version, 'v') : '0.0.0';
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
