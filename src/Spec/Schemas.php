<?php

namespace EduKSX\Core\Spec;

use InvalidArgumentException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Plattform-Schemas aus `spec/` im Repo EduKSX-Architektur (Core C11, SP1), unverändert unter resources/spec/
 * ausgeliefert. Prüft Daten gegen ein Schema, z. B. den Steckbrief (S5), Ereignisse (S4) oder Claims (S1).
 *
 *     Schemas::errors('module-manifest', $manifest);   // [] = gültig, sonst Liste «/pfad: Meldung»
 *     Schemas::assertValid('event', $event);           // wirft InvalidSchemaData
 *
 * Achtung: Ein leeres PHP-Array gilt als JSON-Liste. Leere Objekte (z. B. `eduksx_roles` ohne Rollen) als
 * `new \stdClass` bzw. `(object) []` übergeben, rohe JSON-Texte mit json_decode() ohne `true` lesen.
 */
final class Schemas
{
    public const BASE_ID = 'https://docs.eduksx.ch/spec/';

    public const NAMES = ['event', 'health', 'module-manifest', 'oidc-claims', 'signature'];

    private static ?Validator $validator = null;

    public static function directory(): string
    {
        return dirname(__DIR__, 2).'/resources/spec';
    }

    public static function path(string $name, string $version = 'v1'): string
    {
        if (! in_array($name, self::NAMES, true)) {
            throw new InvalidArgumentException("Unbekanntes Schema «{$name}». Bekannt: ".implode(', ', self::NAMES));
        }

        return self::directory()."/{$version}/{$name}.schema.json";
    }

    /** @return array{repository: string, release: string, commit: string, date: string} Herkunft der Schemas */
    public static function source(): array
    {
        $source = json_decode((string) file_get_contents(self::directory().'/SOURCE.json'), true, flags: JSON_THROW_ON_ERROR);

        return array_intersect_key($source, array_flip(['repository', 'release', 'commit', 'date']));
    }

    /**
     * Fehler beim Prüfen gegen das Schema; leer, wenn gültig.
     *
     * @return list<string>
     */
    public static function errors(string $name, mixed $data, string $version = 'v1'): array
    {
        self::path($name, $version);
        // Schema-Prüfung arbeitet auf JSON-Objekten, nicht auf PHP-Arrays
        $json = json_decode((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        $result = self::validator()->validate($json, self::BASE_ID."{$version}/{$name}.schema.json");
        if ($result->isValid()) {
            return [];
        }
        $out = [];
        foreach ((new ErrorFormatter)->format($result->error(), true) as $pointer => $messages) {
            foreach ((array) $messages as $message) {
                $out[] = ($pointer === '' ? '/' : $pointer).': '.$message;
            }
        }

        return $out;
    }

    public static function isValid(string $name, mixed $data, string $version = 'v1'): bool
    {
        return self::errors($name, $data, $version) === [];
    }

    public static function assertValid(string $name, mixed $data, string $version = 'v1'): void
    {
        $errors = self::errors($name, $data, $version);
        if ($errors !== []) {
            throw new InvalidSchemaData($name, $errors);
        }
    }

    private static function validator(): Validator
    {
        if (self::$validator === null) {
            $validator = new Validator;
            $validator->resolver()->registerPrefix(self::BASE_ID, self::directory().'/');
            // Formate (uuid, email, uri, date-time) prüfen, alle Fehler sammeln
            $validator->parser()->setOption('defaultDraft', '2020-12');
            $validator->setMaxErrors(20);
            $validator->setStopAtFirstError(false);
            self::$validator = $validator;
        }

        return self::$validator;
    }
}
