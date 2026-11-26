<?php

namespace EduKSX\Core\Security;

use EduKSX\Core\Data\Actor;
use EduKSX\Core\Data\DataChanges;
use EduKSX\Core\Logging\MaskPersonalData;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sicherheitsprotokoll (DB-Standard A7, DB-E7, KONZEPT-PROTOKOLLIERUNG §3): Anmeldungen, Rechteänderungen, Debug ein
 * und aus, Exporte, Protokollpakete, Lesezugriffe auf besonders schützenswerte Daten (DB B11).
 *
 * - Einträge sind unveränderlich und **hash-verkettet**: jeder Eintrag trägt den Hash des vorherigen. verify() findet
 *   geänderte, gelöschte oder eingeschobene Einträge.
 * - Prüfpunkt (letzter Hash) täglich ausserhalb der Datenbank: Befehl `eduksx:sicherheitsprotokoll` schreibt ihn ins
 *   Protokoll (Protokollspeicher des Betriebs, Leitstand).
 * - Jeder Eintrag geht zusätzlich als Kopie ins Anwendungsprotokoll (`eduksx.security.<aktion>`).
 * - Angaben (`details`) werden maskiert; Inhalte, Tokens und Geheimnisse gehören nie hinein.
 * - Frist 1 Jahr (DB-E15), danach löscht `eduksx:sicherheitsprotokoll --prune`; die Kette beginnt dann beim ältesten
 *   verbliebenen Eintrag.
 *
 *     SecurityLog::record('roles.changed', $organizationId, ['user_id' => $id, 'role' => 'editor'], 'Antrag 12');
 */
final class SecurityLog
{
    /** Aktionen: Kleinbuchstaben, mit Punkt gegliedert, z. B. `debug.enabled`, `logs.package_created`. */
    public const ACTION_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    private const ATTEMPTS = 5;

    /**
     * @param  array<string, mixed>  $details  IDs und Kennzahlen, keine Inhalte
     * @return string event_id
     */
    public static function record(string $action, ?string $organizationId = null, array $details = [], ?string $reason = null, ?Actor $actor = null): string
    {
        if (preg_match(self::ACTION_PATTERN, $action) !== 1 || strlen($action) > 60) {
            throw new InvalidArgumentException("Aktion «{$action}» passt nicht zum Muster (z. B. debug.enabled).");
        }
        $actor ??= DataChanges::actor();
        $mask = new MaskPersonalData((array) config('eduksx.logging.mask_keys', []));
        $details = $mask->maskArray($details);
        $reason = $reason !== null && $reason !== '' ? mb_substr(MaskPersonalData::maskText($reason), 0, 500) : null;
        $requestId = Context::get('request_id');
        $eventId = (string) Str::uuid7();

        $row = [
            'event_id' => $eventId,
            'occurred_at' => now()->utc()->toDateTimeString(),
            'organization_id' => $organizationId !== null ? strtolower($organizationId) : null,
            'action' => $action,
            'actor_type' => mb_substr($actor->type, 0, 20),
            'actor_id' => $actor->id !== null ? strtolower($actor->id) : null,
            'actor_ref' => $actor->id !== null ? self::actorRef($eventId, strtolower($actor->id)) : null,
            'reason' => $reason,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_id' => is_string($requestId) ? mb_substr($requestId, 0, 100) : null,
        ];

        // Gleichzeitige Einträge: previous_hash ist eindeutig – wer verliert, hängt sich an den neuen letzten Eintrag
        for ($attempt = 1; ; $attempt++) {
            try {
                DB::transaction(function () use (&$row): void {
                    $last = DB::table(self::table())->orderByDesc('id')->lockForUpdate()->value('hash');
                    $row['previous_hash'] = is_string($last) ? $last : null;
                    $row['hash'] = self::hash($row);
                    DB::table(self::table())->insert($row);
                });
                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::ATTEMPTS) {
                    throw $e;
                }
                usleep(random_int(1_000, 20_000));
            }
        }

        Log::info('eduksx.security.'.$action, array_filter([
            'security_event_id' => $eventId,
            'organization_id' => $row['organization_id'],
            'actor_type' => $row['actor_type'],
            'details' => $details === [] ? null : $details,
        ], fn (mixed $v): bool => $v !== null));

        return $eventId;
    }

    /**
     * Kette prüfen, vom ältesten verbliebenen Eintrag an.
     *
     * @return array{valid: bool, entries: int, last_event_id: ?string, last_hash: ?string, broken_at: ?string}
     */
    public static function verify(): array
    {
        $previous = null;
        $count = 0;
        $last = null;
        $broken = null;
        DB::table(self::table())->orderBy('id')->chunk(500, function ($rows) use (&$previous, &$count, &$last, &$broken): bool {
            foreach ($rows as $row) {
                $data = (array) $row;
                $linked = $count === 0 || $data['previous_hash'] === $previous;
                $actorOk = $data['actor_id'] === null || hash_equals((string) $data['actor_ref'], self::actorRef((string) $data['event_id'], strtolower((string) $data['actor_id'])));
                if (! $linked || ! $actorOk || ! hash_equals((string) $data['hash'], self::hash($data))) {
                    $broken = (string) $data['event_id'];

                    return false;
                }
                $previous = (string) $data['hash'];
                $last = (string) $data['event_id'];
                $count++;
            }

            return true;
        });

        return [
            'valid' => $broken === null,
            'entries' => $count,
            'last_event_id' => $last,
            'last_hash' => $broken === null ? $previous : null,
            'broken_at' => $broken,
        ];
    }

    /**
     * Prüfpunkt für die Ablage ausserhalb der Datenbank (A7): Prüfung und letzter Hash als Protokolleintrag.
     *
     * @return array{valid: bool, entries: int, last_event_id: ?string, last_hash: ?string, broken_at: ?string}
     */
    public static function checkpoint(): array
    {
        $result = self::verify();
        $result['valid']
            ? Log::info('eduksx.security.checkpoint', $result)
            : Log::critical('eduksx.security.chain_broken', $result);

        return $result;
    }

    /** Einträge älter als die Frist löschen (DB-E15: 1 Jahr). */
    public static function prune(?int $days = null): int
    {
        $days ??= (int) config('eduksx.security_log.retention_days', 365);

        return DB::table(self::table())->where('occurred_at', '<', now()->utc()->subDays($days)->toDateTimeString())->delete();
    }

    /** Kontolöschung: Bezug auf die Person entfernen; das Pseudonym `actor_ref` hält die Kette gültig. */
    public static function forget(string $actorId): int
    {
        return DB::table(self::table())->where('actor_id', strtolower($actorId))->update(['actor_id' => null]);
    }

    /** Tabelle angelegt (Migration veröffentlicht)? */
    public static function installed(): bool
    {
        return Schema::hasTable(self::table());
    }

    public static function table(): string
    {
        return (string) config('eduksx.security_log.table', 'security_events');
    }

    /** @param  array<string, mixed>  $row */
    private static function hash(array $row): string
    {
        $fields = ['event_id', 'occurred_at', 'organization_id', 'action', 'actor_type', 'actor_ref', 'reason', 'details', 'request_id', 'previous_hash'];
        $values = [];
        foreach ($fields as $field) {
            $value = $row[$field] ?? null;
            $values[] = $value === null ? null : (string) $value;
        }

        return hash('sha256', (string) json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function actorRef(string $eventId, string $actorId): string
    {
        return hash('sha256', $eventId.'|'.$actorId);
    }
}
