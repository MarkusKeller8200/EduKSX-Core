<?php

namespace EduKSX\Core\Events;

/**
 * Laravel-Event für ein empfangenes, geprüftes S4-Ereignis. Zweimal ausgelöst: als diese Klasse (alle Arten) und
 * unter dem Namen `eduksx:<art>` (eine Art):
 *
 *     Event::listen('eduksx:ident.user.deleted', function (PlatformEventReceived $e) { … $e->data['user_id'] … });
 *
 * Läuft in derselben Transaktion wie die Idempotenz-Sperre: Wirft ein Listener, wird das Ereignis nicht als
 * verarbeitet vermerkt, die Antwort ist 500 und der Absender stellt es erneut zu.
 */
final class PlatformEventReceived
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $source,
        public readonly string $time,
        public readonly ?string $subject,
        public readonly array $data,
    ) {}

    public function eventName(): string
    {
        return 'eduksx:'.$this->type;
    }
}
