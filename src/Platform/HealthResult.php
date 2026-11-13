<?php

namespace EduKSX\Core\Platform;

/** Ergebnis einer Prüfung von /health. Meldung höchstens 200 Zeichen, fester Text ohne Hostnamen oder Personendaten. */
final class HealthResult
{
    private function __construct(public readonly string $status, public readonly ?string $message) {}

    public static function ok(?string $message = null): self
    {
        return new self('ok', $message);
    }

    public static function degraded(string $message): self
    {
        return new self('degraded', $message);
    }

    public static function fail(string $message): self
    {
        return new self('fail', $message);
    }

    /** @return array{status: string, message?: string, duration_ms: int} */
    public function toArray(int $durationMs): array
    {
        return array_filter([
            'status' => $this->status,
            'message' => $this->message !== null ? mb_substr($this->message, 0, 200) : null,
            'duration_ms' => max(0, $durationMs),
        ], fn ($v) => $v !== null);
    }
}
