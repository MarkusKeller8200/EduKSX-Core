<?php

namespace EduKSX\Core\Auth;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Begrenzung der Anmeldeversuche je Konto und Adresse (KONZEPT-ANMELDUNG §3, Vorbild Glossary LoginController):
 * 5 Fehlversuche je Konto und Adresse, 20 je Adresse in 60 Sekunden. Fehlertexte bleiben generisch.
 *
 *     if ($wait = LoginThrottle::blockedFor($email, $request->ip())) { … «in $wait Sekunden nochmals» … }
 *     $ok ? LoginThrottle::clear($email, $ip) : LoginThrottle::hit($email, $ip);
 */
final class LoginThrottle
{
    public const PER_ACCOUNT = 5;

    public const PER_ADDRESS = 20;

    public const DECAY = 60;

    /** Sekunden bis zum nächsten Versuch, 0 = erlaubt. */
    public static function blockedFor(string $account, ?string $address): int
    {
        foreach ([[self::accountKey($account, $address), self::PER_ACCOUNT], [self::addressKey($address), self::PER_ADDRESS]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return max(1, RateLimiter::availableIn($key));
            }
        }

        return 0;
    }

    public static function hit(string $account, ?string $address): void
    {
        RateLimiter::hit(self::accountKey($account, $address), self::DECAY);
        RateLimiter::hit(self::addressKey($address), self::DECAY);
    }

    public static function clear(string $account, ?string $address): void
    {
        RateLimiter::clear(self::accountKey($account, $address));
    }

    private static function accountKey(string $account, ?string $address): string
    {
        return 'eduksx-login:'.hash('sha256', Str::lower(trim($account)).'|'.$address);
    }

    private static function addressKey(?string $address): string
    {
        return 'eduksx-login-ip:'.hash('sha256', (string) $address);
    }
}
