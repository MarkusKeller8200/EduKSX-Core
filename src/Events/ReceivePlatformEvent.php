<?php

namespace EduKSX\Core\Events;

use EduKSX\Core\Http\ProblemException;
use EduKSX\Core\Spec\Schemas;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Empfang von S4-Ereignissen `POST {eduksx.events.path}` (Plattform S4, SP6). Ohne Token – die Signatur weist den
 * Absender aus. Reihenfolge: Grösse → Signatur → JSON → Schema → Absender → Idempotenz → Laravel-Event.
 *
 * Antworten: 204 verarbeitet (auch bei Wiederholung), 401 Signatur, 403 unbekannter Absender, 413 zu gross,
 * 422 ungültiges Ereignis, 500 Fehler im Listener (Absender wiederholt).
 */
final class ReceivePlatformEvent
{
    public function __invoke(Request $request): Response
    {
        $body = $request->getContent();
        if (strlen($body) > (int) config('eduksx.events.max_bytes', 262144)) {
            throw ProblemException::common(413, 'payload_too_large', 'Ereignis zu gross.');
        }

        try {
            Signer::verify(
                $request->headers->get(Signer::HEADER),
                $body,
                array_values((array) config('eduksx.events.keys', [])),
                (int) config('eduksx.events.tolerance', Signer::TOLERANCE),
            );
        } catch (InvalidSignature $e) {
            Log::warning('eduksx.events.signature_rejected', ['reason' => $e->reason]);
            throw ProblemException::common(401, 'invalid_signature', 'Signatur fehlt, ist falsch oder zu alt.');
        }

        $event = json_decode($body);
        if (! $event instanceof \stdClass) {
            throw ProblemException::common(422, 'invalid_event', 'Kein JSON-Objekt.');
        }
        $errors = Schemas::errors('event', $event);
        if ($errors !== []) {
            Log::warning('eduksx.events.invalid', ['type' => is_string($event->type ?? null) ? $event->type : null, 'errors' => count($errors)]);
            $byField = [];
            foreach ($errors as $error) {
                [$pointer, $message] = array_pad(explode(': ', $error, 2), 2, '');
                $byField[$pointer][] = $message;
            }
            throw ProblemException::common(422, 'invalid_event', 'Ereignis entspricht nicht spec/v1/event.schema.json.', [], $byField);
        }

        $sources = array_values((array) config('eduksx.events.sources', []));
        if ($sources !== [] && ! in_array($event->source, $sources, true)) {
            Log::warning('eduksx.events.unknown_source', ['type' => $event->type]);
            throw ProblemException::common(403, 'unknown_source', 'Absender nicht zugelassen.');
        }

        $received = new PlatformEventReceived(
            $event->id,
            $event->type,
            $event->source,
            $event->time,
            isset($event->subject) && is_string($event->subject) ? $event->subject : null,
            json_decode((string) json_encode($event->data), true) ?? [],
        );

        $new = DB::transaction(function () use ($received): bool {
            $inserted = ReceivedEvent::query()->insertOrIgnore([
                'event_id' => $received->id,
                'type' => $received->type,
                'source' => mb_substr($received->source, 0, 255),
                'received_at' => now()->utc()->toDateTimeString(),
            ]);
            if ($inserted === 0) {
                return false;
            }
            Event::dispatch($received);
            Event::dispatch($received->eventName(), [$received]);

            return true;
        });

        Log::info($new ? 'eduksx.events.processed' : 'eduksx.events.duplicate', ['id' => $received->id, 'type' => $received->type]);

        return new Response('', 204);
    }
}
