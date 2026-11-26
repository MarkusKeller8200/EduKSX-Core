<?php

namespace EduKSX\Core\Auth;

use EduKSX\Core\Settings\Settings;
use Illuminate\Validation\Rules\Password;

/**
 * Passwortregeln für das lokale Login (KONZEPT-ANMELDUNG §3, NIST SP 800-63B-4): mindestens 15 Zeichen ohne bzw. 12 mit
 * zweitem Faktor, bis 128 Zeichen, keine Zeichenklassen, kein periodischer Wechsel. Abgleich mit einer Liste bekannter
 * Passwörter (Datei, eine Zeile je Passwort; config('eduksx.auth.password_blocklist')) und – nur per Schalter
 * `auth.password_breach_check` (Vorgabe aus, AN5) – mit dem externen Dienst von Laravel (`uncompromised`).
 *
 *     $request->validate(['password' => ['required', 'confirmed', PasswordRules::rule($user->hasTwoFactor())]]);
 */
final class PasswordRules
{
    public const MIN_WITHOUT_SECOND_FACTOR = 15;

    public const MIN_WITH_SECOND_FACTOR = 12;

    public const MAX = 128;

    /** @var array<string, true>|null */
    private static ?array $blocklist = null;

    public static function rule(bool $hasSecondFactor = false): Password
    {
        $rule = Password::min($hasSecondFactor ? self::MIN_WITH_SECOND_FACTOR : self::MIN_WITHOUT_SECOND_FACTOR)
            ->max(self::MAX)
            ->rules([function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && self::isBlocked($value)) {
                    $fail(__('eduksx::auth.password_blocked'));
                }
            }]);

        return self::breachCheck() ? $rule->uncompromised() : $rule;
    }

    public static function isBlocked(string $password): bool
    {
        if (self::$blocklist === null) {
            self::$blocklist = [];
            $path = config('eduksx.auth.password_blocklist');
            if (is_string($path) && is_file($path)) {
                $handle = fopen($path, 'rb');
                while ($handle !== false && ($line = fgets($handle)) !== false) {
                    $line = mb_strtolower(trim($line));
                    if ($line !== '' && ! str_starts_with($line, '#')) {
                        self::$blocklist[$line] = true;
                    }
                }
                if ($handle !== false) {
                    fclose($handle);
                }
            }
        }

        return isset(self::$blocklist[mb_strtolower($password)]);
    }

    /** Liste neu laden (Tests, geänderte Datei). */
    public static function flush(): void
    {
        self::$blocklist = null;
    }

    private static function breachCheck(): bool
    {
        $settings = app(Settings::class);

        return array_key_exists('auth.password_breach_check', $settings->definitions()) && $settings->bool('auth.password_breach_check');
    }
}
