<?php

namespace EduKSX\Core\Http;

use InvalidArgumentException;
use RuntimeException;

/**
 * Modul-eigener Fehler mit stabilem, englischem Code (API-Standard A6). Wird zu
 * `{problem_base_url}/{modul}/{code}`, z. B. `…/problems/glossary/term_merged`.
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
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $problemCode) !== 1) {
            throw new InvalidArgumentException("Fehlercode «{$problemCode}» muss snake_case und englisch sein.");
        }
        parent::__construct($detail);
    }
}
