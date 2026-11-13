<?php

namespace EduKSX\Core\Data;

/**
 * Datenklasse je Spalte (DB-Standard B2). Das Modul legt sie für alle eigenen Tabellen fest, z. B. im
 * AppServiceProvider:
 *
 *     DataClasses::define([
 *         'terms' => ['id' => DataClass::Internal, 'name' => DataClass::Public, 'note' => 'internal'],
 *     ]);
 *
 * Daraus folgen die Regeln der Historie (data_changes), später Export und Löschung. Unbekannte Spalten gelten
 * vorsichtig als personenbezogen; der Konformitätstest (assertDataClassesComplete) meldet sie.
 */
final class DataClasses
{
    /** Tabellen von eduksx/core selbst. */
    public const CORE = [
        'data_changes' => [
            'id' => DataClass::Internal,
            'event_id' => DataClass::Internal,
            'subject_type' => DataClass::Internal,
            'subject_id' => DataClass::Internal,
            'organization_id' => DataClass::Internal,
            'action' => DataClass::Internal,
            'changes' => DataClass::Internal,
            'actor_type' => DataClass::Internal,
            'actor_id' => DataClass::Internal,
            'actor_label' => DataClass::Personal,
            'source' => DataClass::Internal,
            'transaction_id' => DataClass::Internal,
            'request_id' => DataClass::Internal,
            'schema_version' => DataClass::Internal,
            'occurred_at' => DataClass::Internal,
        ],
        'received_events' => [
            'event_id' => DataClass::Internal,
            'type' => DataClass::Internal,
            'source' => DataClass::Internal,
            'received_at' => DataClass::Internal,
        ],
    ];

    /** @var array<string, array<string, DataClass>> */
    private static array $map = [];

    /**
     * Datenklassen festlegen bzw. ergänzen (spätere Aufrufe überschreiben einzelne Spalten).
     *
     * @param  array<string, array<string, DataClass|string>>  $map
     */
    public static function define(array $map): void
    {
        foreach ($map as $table => $columns) {
            foreach ($columns as $column => $class) {
                self::$map[$table][$column] = $class instanceof DataClass ? $class : DataClass::from($class);
            }
        }
    }

    /** Nur für Tests: zurück auf die Tabellen von Core. */
    public static function reset(): void
    {
        self::$map = [];
    }

    /** @return array<string, array<string, DataClass>> */
    public static function map(): array
    {
        $map = self::CORE;
        foreach (self::$map as $table => $columns) {
            $map[$table] = array_merge($map[$table] ?? [], $columns);
        }

        return $map;
    }

    public static function has(string $table, string $column): bool
    {
        return isset(self::$map[$table][$column]) || isset(self::CORE[$table][$column]);
    }

    public static function of(string $table, string $column): DataClass
    {
        return self::$map[$table][$column] ?? self::CORE[$table][$column] ?? DataClass::Personal;   // unbekannt: vorsichtig
    }

    /**
     * Änderungen nach Datenklasse (Historie): öffentlich/intern mit Werten, sonst nur «changed».
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     * @return array<string, array{old: mixed, new: mixed}|string>
     */
    public static function filterChanges(string $table, array $changes): array
    {
        $out = [];
        foreach ($changes as $column => $change) {
            $out[$column] = self::of($table, $column)->keepsValues() ? $change : 'changed';
        }

        return $out;
    }
}
