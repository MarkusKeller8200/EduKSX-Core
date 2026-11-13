<?php

namespace EduKSX\Core\Testing;

use EduKSX\Core\Events\CloudEvent;
use EduKSX\Core\Events\Signer;
use Illuminate\Testing\TestResponse;

/**
 * Test-Absender für S4-Ereignisse (Fake-Ident). Im Modul-Test:
 *
 *     use SendsPlatformEvents;
 *
 *     $this->postPlatformEvent('ident.user.deleted', ['user_id' => $id, 'subject' => '123'])->assertNoContent();
 *
 * Signiert mit dem ersten Schlüssel aus eduksx.events.keys, sofern kein anderer angegeben ist.
 */
trait SendsPlatformEvents
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function postPlatformEvent(string $type, array $data, ?string $subject = null, ?string $id = null, ?string $key = null, ?int $time = null): TestResponse
    {
        $event = CloudEvent::make('https://ident.eduksx.example', $type, $data, $subject, $id);

        return $this->postRawPlatformEvent((string) json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $key, $time);
    }

    /** Roher Body, z. B. für ungültige Ereignisse; `$key = ''` sendet ohne Signatur. */
    public function postRawPlatformEvent(string $body, ?string $key = null, ?int $time = null): TestResponse
    {
        $key ??= (string) (config('eduksx.events.keys')[0] ?? '');
        $headers = ['CONTENT_TYPE' => 'application/cloudevents+json', 'HTTP_ACCEPT' => 'application/json'];
        if ($key !== '') {
            $headers['HTTP_'.strtoupper(str_replace('-', '_', Signer::HEADER))] = Signer::header($body, $key, $time);
        }

        return $this->call('POST', '/'.ltrim((string) config('eduksx.events.path'), '/'), [], [], [], $headers, $body);
    }
}
