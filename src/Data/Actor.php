<?php

namespace EduKSX\Core\Data;

/**
 * Wer eine Änderung ausgelöst hat (Historie). `type` z. B. user, admin, service, system; `id` als UUID (Plattform-ID
 * der Person bzw. lokale ID); `label` nur, wenn das Modul eine Anzeige braucht – sie ist personenbezogen (B2).
 */
final class Actor
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $id = null,
        public readonly ?string $label = null,
    ) {}

    public static function system(): self
    {
        return new self('system');
    }
}
