<?php

namespace EduKSX\Core\Data;

use Closure;
use EduKSX\Core\Http\Middleware\AssignRequestId;
use EduKSX\Core\Module;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Änderungshistorie (DB-Standard A7) für Tabellen ohne eigenes Revisionssystem: wer, wann, was – in derselben
 * Transaktion wie die Änderung. Werte nur nach Datenklasse (B2): personenbezogen, besonders schützenswert und geheim
 * nur «changed». Herausgelöst aus dem Glossary (app/Data/DataChanges.php), Verhalten unverändert.
 *
 * Modelle nutzen den Trait RecordsDataChanges. Wer handelt, bestimmt das Modul:
 *
 *     DataChanges::resolveActorUsing(fn () => new Actor('editor', auth()->id(), null));
 *
 * Ohne Angabe: angemeldete Person als `user` (ID nur, wenn sie eine UUID ist), sonst `system`.
 */
final class DataChanges
{
    /** Spalten, die nie als Änderung zählen. */
    private const IGNORED = ['updated_at', 'lock_version'];

    /** Erlaubte Quellen (CHECK in der Tabelle). */
    public const SOURCES = ['web', 'api', 'import', 'mcp', 'job', 'system', 'migration'];

    public const DELETED_LABEL = 'Gelöschtes Konto';

    private static ?string $transaction = null;

    private static ?string $source = null;

    /** @var (Closure(): ?Actor)|null */
    private static ?Closure $actorResolver = null;

    /** @param  (Closure(): ?Actor)|null  $resolver */
    public static function resolveActorUsing(?Closure $resolver): void
    {
        self::$actorResolver = $resolver;
    }

    /**
     * Änderungen innerhalb von $callback mit einer festen Quelle, z. B. «import» oder «mcp».
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withSource(string $source, Closure $callback): mixed
    {
        if (! in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException("Unbekannte Quelle «{$source}».");
        }
        $previous = self::$source;
        self::$source = $source;
        try {
            return $callback();
        } finally {
            self::$source = $previous;
        }
    }

    /** Eintrag für ein Eloquent-Modell (Trait RecordsDataChanges). */
    public static function record(Model $model, string $action): void
    {
        $table = $model->getTable();
        $changes = [];
        $trash = ['deleted_at', 'deleted_by_id'];
        if ($action === 'created') {
            foreach ($model->getAttributes() as $column => $value) {
                if (! in_array($column, self::IGNORED, true) && $value !== null) {
                    $changes[$column] = ['old' => null, 'new' => $value];
                }
            }
        } elseif ($action === 'updated') {
            foreach (array_keys($model->getChanges()) as $column) {
                if (! in_array($column, self::IGNORED, true)) {
                    $changes[$column] = ['old' => $model->getPrevious()[$column] ?? null, 'new' => $model->getAttribute($column)];
                }
            }
            // Wiederherstellen aus dem Papierkorb schreibt «restored» (eigenes Ereignis)
            if ($changes === [] || array_diff(array_keys($changes), $trash) === []) {
                return;
            }
        } elseif ($action === 'deleted' && method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            // in den Papierkorb
            foreach ($trash as $column) {
                $changes[$column] = ['old' => null, 'new' => $model->getAttribute($column)];
            }
        }
        $organization = $model->getAttribute('organization_id');
        if ($table === 'organizations') {
            $organization = $model->getKey();
        }
        // Kontolöschung durch die Person selbst: kein Bezug auf das gelöschte Konto zurücklassen
        $self = $model instanceof Authenticatable && $action === 'deleted' && auth()->id() !== null && auth()->id() === $model->getKey();
        self::write($table, (string) $model->getKey(), $action, DataClasses::filterChanges($table, $changes), is_string($organization) ? $organization : null, $self);
    }

    /**
     * Eintrag ohne Modell, z. B. Sammeleintrag bei Schreibzugriffen am Modell vorbei (Anzahl statt Einzelwerte).
     *
     * @param  array<string, mixed>  $changes  bereits nach Datenklasse gefiltert
     */
    public static function write(string $table, string $id, string $action, array $changes, ?string $organizationId = null, bool $anonymous = false): void
    {
        $actor = $anonymous ? Actor::system() : self::actor();
        DB::table(self::table())->insert([
            'event_id' => (string) Str::uuid7(),
            'subject_type' => $table,
            'subject_id' => $id,
            'organization_id' => $organizationId,
            'action' => $action,
            'changes' => $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE),
            'actor_type' => mb_substr($actor->type, 0, 20),
            'actor_id' => $actor->id,
            'actor_label' => $actor->label !== null ? mb_substr($actor->label, 0, 120) : null,
            'source' => self::source(),
            'transaction_id' => self::transaction(),
            'request_id' => self::requestId(),
            'schema_version' => mb_substr(Module::version(), 0, 20),
            'occurred_at' => now()->utc()->toDateTimeString(),
        ]);
    }

    /** Kontolöschung: Bezug und Bezeichnung der Person in der Historie entfernen (B2: Bezug pseudonymisieren). */
    public static function forget(string $actorId): void
    {
        DB::table(self::table())->where('actor_id', $actorId)->update(['actor_id' => null, 'actor_label' => self::DELETED_LABEL]);
    }

    public static function table(): string
    {
        return (string) config('eduksx.history.table', 'data_changes');
    }

    private static function actor(): Actor
    {
        if (self::$actorResolver !== null) {
            return (self::$actorResolver)() ?? Actor::system();
        }
        $id = auth()->id();
        if ($id === null) {
            return Actor::system();
        }

        return new Actor('user', Str::isUuid((string) $id) ? (string) $id : null);
    }

    /** Request-ID nur bei echten HTTP-Anfragen (von AssignRequestId gesetzt), nicht in Befehlen und Jobs. */
    private static function requestId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }
        $id = request()->attributes->get(AssignRequestId::ATTRIBUTE);

        return is_string($id) ? mb_substr($id, 0, 100) : null;
    }

    private static function source(): string
    {
        if (self::$source !== null) {
            return self::$source;
        }
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return 'job';
        }

        return request()->is('api/*') ? 'api' : 'web';
    }

    /** Gemeinsame Kennung aller Einträge einer Datenbank-Transaktion; ausserhalb je Eintrag neu. */
    private static function transaction(): string
    {
        if (DB::transactionLevel() === 0) {
            return (string) Str::uuid7();
        }
        $id = self::$transaction;
        if ($id === null) {
            $id = self::$transaction = (string) Str::uuid7();
            DB::afterCommit(function (): void {
                self::$transaction = null;
            });
        }

        return $id;
    }
}
