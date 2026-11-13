<?php

namespace EduKSX\Core\Events;

use RuntimeException;

/** Signatur eines S4-Ereignisses fehlt, ist falsch oder zu alt. `reason`: missing, malformed, expired, mismatch, no_key. */
final class InvalidSignature extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Signatur ungültig ({$reason}).");
    }
}
