<?php

namespace EduKSX\Core\Auth;

use EduKSX\Core\Data\Actor;
use EduKSX\Core\Security\SecurityLog;
use EduKSX\Core\Settings\Settings;

/**
 * Baustein «Anmeldung», Stufe 1 (KONZEPT-ANMELDUNG §3, §5): Einstellungen aus AN5 und Einträge im Sicherheitsprotokoll.
 * Masken, Controller und Konten bleiben vorerst im Modul; Core liefert TOTP, Wiederherstellungscodes, Passwortregeln,
 * Begrenzung der Versuche und die Sitzungsdauer (Middleware SessionTimeout). Aktiv mit `eduksx.auth.local = true`.
 */
final class LocalLogin
{
    /** Einstellungen nach AN5 (BESCHLOSSEN 26.11.2026): Höchstwerte, kürzer wählbar. @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            'auth.session_idle_admin_minutes' => ['type' => 'int', 'label' => 'Leerlauf in der Administration', 'default' => 30, 'min' => 5, 'max' => 30, 'unit' => 'Minuten', 'group' => 'auth'],
            'auth.session_idle_minutes' => ['type' => 'int', 'label' => 'Leerlauf sonst', 'default' => 480, 'min' => 15, 'max' => 480, 'unit' => 'Minuten', 'group' => 'auth'],
            'auth.session_absolute_minutes' => ['type' => 'int', 'label' => 'Sitzung höchstens', 'default' => 720, 'min' => 60, 'max' => 720, 'unit' => 'Minuten', 'group' => 'auth'],
            'auth.federation_grace_days' => ['type' => 'int', 'label' => 'Übergangsfrist beim Umschalten auf Anbieter', 'default' => 30, 'min' => 1, 'max' => 30, 'unit' => 'Tage', 'group' => 'auth'],
            'auth.password_breach_check' => ['type' => 'bool', 'label' => 'Passwörter beim externen Dienst prüfen', 'default' => false, 'group' => 'auth',
                'hint' => 'Sendet die ersten 5 Zeichen des SHA-1-Hashs an haveibeenpwned.com (k-Anonymität).'],
        ];
    }

    public static function register(Settings $settings): void
    {
        foreach (self::definitions() as $key => $definition) {
            $settings->define($key, $definition);
        }
    }

    /**
     * Ereignis der Anmeldung ins Sicherheitsprotokoll (§3 «Protokoll»): login.succeeded, login.failed, login.locked,
     * password.reset, mfa.enabled, mfa.reset, mfa.recovery_used, auth.mode_changed, breakglass.login, session.expired.
     *
     * @param  array<string, mixed>  $details  IDs, keine E-Mail-Adressen (werden ohnehin maskiert)
     */
    public static function log(string $event, ?string $organizationId = null, ?Actor $actor = null, array $details = [], ?string $reason = null): string
    {
        return SecurityLog::record($event, $organizationId, $details, $reason, $actor);
    }
}
