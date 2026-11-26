<?php

namespace EduKSX\Core\Http\Middleware;

use Closure;
use EduKSX\Core\Auth\LocalLogin;
use EduKSX\Core\Data\Actor;
use EduKSX\Core\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sitzungsdauer nach KONZEPT-ANMELDUNG §3 (AN5): Leerlauf höchstens 30 Minuten in der Administration, 8 Stunden sonst,
 * absolut 12 Stunden; kürzer über die Einstellungen `auth.session_*`. Im Modul nach `StartSession` in der Gruppe
 * `web`: `$middleware->web(append: [\EduKSX\Core\Http\Middleware\SessionTimeout::class])`.
 * Administration = Pfade aus config('eduksx.auth.admin_paths') (Vorgabe admin, admin/*).
 */
final class SessionTimeout
{
    public const STARTED = 'eduksx.session.started_at';

    public const SEEN = 'eduksx.session.last_seen_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || Auth::guest()) {
            return $next($request);
        }
        $session = $request->session();
        $now = now()->getTimestamp();
        $started = (int) $session->get(self::STARTED, $now);
        $seen = (int) $session->get(self::SEEN, $now);
        $admin = $request->is(...(array) config('eduksx.auth.admin_paths', ['admin', 'admin/*']));

        $idle = $this->minutes($admin ? 'auth.session_idle_admin_minutes' : 'auth.session_idle_minutes', $admin ? 30 : 480);
        $absolute = $this->minutes('auth.session_absolute_minutes', 720);
        if ($now - $seen > $idle * 60 || $now - $started > $absolute * 60) {
            $id = Auth::id();
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();
            if (config('eduksx.auth.local', false)) {
                LocalLogin::log('session.expired', null, new Actor('user', is_string($id) && Str::isUuid($id) ? $id : null), ['area' => $admin ? 'admin' : 'app']);
            }

            return $request->expectsJson()
                ? response()->json(['message' => __('eduksx::auth.session_expired')], 401)
                : redirect()->guest((string) config('eduksx.auth.login_path', '/anmelden'))->with('status', __('eduksx::auth.session_expired'));
        }
        $session->put([self::STARTED => $started, self::SEEN => $now]);

        return $next($request);
    }

    private function minutes(string $key, int $fallback): int
    {
        $settings = app(Settings::class);
        $value = array_key_exists($key, $settings->definitions()) ? $settings->int($key) : $fallback;

        return max(1, min($fallback, $value));
    }
}
