<?php

namespace EduKSX\Core\Spec;

use RuntimeException;

/** Daten entsprechen nicht dem Plattform-Schema. */
final class InvalidSchemaData extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly string $schema, public readonly array $errors)
    {
        parent::__construct("Ungültig nach Schema «{$schema}»: ".implode('; ', array_slice($errors, 0, 5)));
    }
}
