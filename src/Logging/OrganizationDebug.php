<?php

namespace EduKSX\Core\Logging;

use Carbon\CarbonImmutable;
use EduKSX\Core\Data\Actor;
use EduKSX\Core\Security\SecurityLog;
use EduKSX\Core\Settings\Settings;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use Throwable;

/**
 * Befristetes Debug je Organisation in test und prod (KONZEPT-PROTOKOLLIERUNG §5, PR2): höchstens 60 Minuten, nur
 * durch den Super-Admin, schaltet sich selbst aus. Ein, Aus und Ablauf stehen mit Grund im Sicherheitsprotokoll; die
 * Maskierung gilt weiter. `APP_DEBUG` bleibt aus – es geht nur um Protokolleinträge der Stufe `debug`.
 *
 * Wer schalten darf, prüft das Modul (Super-Admin aus Ident, bis dahin `system.admin`, KONZEPT-ROLLEN); auf dem Server
 * der Befehl `eduksx:debug`. Welche Organisation eine Anfrage betrifft, setzt das Modul nach der Anmeldung:
 *
 *     Context::add('organization_id', $organization->id);
 *
 * Gespeichert in der Einstellung `eduksx.logging.debug` (Baustein Einstellungen, Historie in data_changes).
 */
final class OrganizationDebug
{
    public const SETTING = 'eduksx.logging.debug';

    public const CONTEXT_KEY = 'organization_id';

    public const MAX_MINUTES = 60;

    /** Für den Baustein Einstellungen (ServiceProvider). @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'type' => 'text', 'max' => 4000, 'default' => '', 'group' => 'system',
            'label' => 'Befristetes Debug je Organisation',
            'hint' => 'Wird über «Debug einschalten» bzw. eduksx:debug gesetzt, nicht von Hand.',
        ];
    }

    public static function enable(string $organizationId, int $minutes, string $reason, Actor $actor): CarbonImmutable
    {
        if ($minutes < 1 || $minutes > self::MAX_MINUTES) {
            throw new InvalidArgumentException('Debug höchstens '.self::MAX_MINUTES.' Minuten.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Ein Grund ist Pflicht (Sicherheitsprotokoll).');
        }
        $organizationId = strtolower($organizationId);
        $until = CarbonImmutable::now('UTC')->addMinutes($minutes)->startOfSecond();
        $sessions = self::sessions();
        $sessions[$organizationId] = $until->toIso8601ZuluString();
        self::store($sessions, $actor);
        SecurityLog::record('debug.enabled', $organizationId, ['minutes' => $minutes, 'until' => $sessions[$organizationId]], $reason, $actor);

        return $until;
    }

    public static function disable(string $organizationId, string $reason, Actor $actor): bool
    {
        $organizationId = strtolower($organizationId);
        $sessions = self::sessions();
        if (! isset($sessions[$organizationId])) {
            return false;
        }
        unset($sessions[$organizationId]);
        self::store($sessions, $actor);
        SecurityLog::record('debug.disabled', $organizationId, [], $reason, $actor);

        return true;
    }

    /** Abgelaufene Sitzungen entfernen und den Ablauf protokollieren (Zeitplan jede Minute). @return list<string> */
    public static function expire(): array
    {
        $sessions = self::sessions();
        $now = CarbonImmutable::now('UTC');
        $expired = [];
        foreach ($sessions as $organizationId => $until) {
            if (CarbonImmutable::parse($until)->lessThanOrEqualTo($now)) {
                $expired[] = $organizationId;
                unset($sessions[$organizationId]);
            }
        }
        if ($expired !== []) {
            self::store($sessions, Actor::system());
            foreach ($expired as $organizationId) {
                SecurityLog::record('debug.expired', $organizationId, [], 'Frist abgelaufen', Actor::system());
            }
        }

        return $expired;
    }

    /** Läuft Debug für die Organisation (bzw. die aus dem Context)? Abgelaufene gelten sofort als aus. */
    public static function active(?string $organizationId = null): bool
    {
        $organizationId ??= Context::get(self::CONTEXT_KEY);
        if (! is_string($organizationId) || $organizationId === '') {
            return false;
        }
        $until = self::until($organizationId);

        return $until !== null && $until->isFuture();
    }

    public static function until(string $organizationId): ?CarbonImmutable
    {
        $value = self::sessions()[strtolower($organizationId)] ?? null;

        return $value !== null ? CarbonImmutable::parse($value) : null;
    }

    /** @return array<string, string> Organisation → Ende (UTC, ISO 8601) */
    public static function sessions(): array
    {
        try {
            $settings = app(Settings::class);
            if (! array_key_exists(self::SETTING, $settings->definitions())) {
                return [];
            }
            $decoded = json_decode($settings->string(self::SETTING), true);
        } catch (Throwable) {
            return [];   // Protokollierung darf nie an der Datenbank scheitern
        }

        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }

    /** @param  array<string, string>  $sessions */
    private static function store(array $sessions, Actor $actor): void
    {
        ksort($sessions);
        app(Settings::class)->set([self::SETTING => $sessions === [] ? '' : (string) json_encode($sessions)], $actor->id);
    }
}
