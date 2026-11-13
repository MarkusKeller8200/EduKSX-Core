<?php

namespace EduKSX\Core\Http;

use InvalidArgumentException;
use RuntimeException;

/**
 * Fehler mit stabilem, englischem Code (API-Standard A6). Modul-eigene Codes werden zu
 * `{problem_base_url}/{modul}/{code}`, z. B. `…/problems/glossary/term_merged`; gemeinsame Codes der Plattform
 * (Core) über ProblemException::common() zu `{problem_base_url}/{code}`.
 */
class ProblemException extends RuntimeException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $problemCode,
        string $detail = '',
        public readonly array $headers = [],
        public readonly bool $common = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $problemCode) !== 1) {
            throw new InvalidArgumentException("Fehlercode «{$problemCode}» muss snake_case und englisch sein.");
        }
        parent::__construct($detail);
    }

    /**
     * Gemeinsamer Fehler der Plattform (ohne Modulschlüssel im Typ), z. B. `invalid_cursor`.
     *
     * @param  array<string, string>  $headers
     */
    public static function common(int $status, string $code, string $detail = '', array $headers = []): self
    {
        return new self($status, $code, $detail, $headers, true);
    }
}
