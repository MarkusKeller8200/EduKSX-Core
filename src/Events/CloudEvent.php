<?php

namespace EduKSX\Core\Events;

use EduKSX\Core\Spec\Schemas;
use Illuminate\Support\Str;

/**
 * S4-Ereignis als CloudEvents 1.0 JSON (spec/v1/event.schema.json). Für Absender (Ident) und Tests:
 *
 *     $event = CloudEvent::make('https://ident.eduksx.ch', 'ident.user.deleted', ['user_id' => $id, 'subject' => $sub]);
 *     $body  = CloudEvent::encode($event);                 // geprüft gegen das Schema
 *     $sig   = Signer::header($body, $schluessel);
 *
 * `data` enthält nur IDs, keine Personendaten (SP7).
 */
final class CloudEvent
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function make(string $source, string $type, array $data, ?string $subject = null, ?string $id = null, ?\DateTimeInterface $time = null): array
    {
        return array_filter([
            'specversion' => '1.0',
            'id' => $id ?? (string) Str::uuid7(),
            'source' => $source,
            'type' => $type,
            'time' => ($time ?? now())->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'datacontenttype' => 'application/json',
            'subject' => $subject,
            'data' => (object) $data,
        ], fn ($v) => $v !== null);
    }

    /**
     * JSON-Text des Ereignisses, vorher gegen das Schema geprüft (wirft InvalidSchemaData).
     *
     * @param  array<string, mixed>  $event
     */
    public static function encode(array $event): string
    {
        Schemas::assertValid('event', $event);

        return (string) json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
