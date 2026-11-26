<?php

namespace EduKSX\Core\Logging;

use Carbon\CarbonImmutable;
use EduKSX\Core\Data\Actor;
use EduKSX\Core\Module;
use EduKSX\Core\Platform\Health;
use EduKSX\Core\Platform\Manifest;
use EduKSX\Core\Security\SecurityLog;
use RuntimeException;
use Throwable;

/**
 * Protokollpaket für den Support bei Selbst-Hosting (KONZEPT-PROTOKOLLIERUNG §9, PR4): Protokolle eines Zeitraums
 * (maskiert), Version, Steckbrief und /health – gepackt und **verschlüsselt** für den öffentlichen Schlüssel des
 * Supports (libsodium «sealed box», X25519). Der Kunde sendet die Datei selbst; die Erzeugung steht im
 * Sicherheitsprotokoll. Pakete gelten als «intern» und werden nach dem Fall gelöscht, spätestens nach 30 Tagen
 * (`eduksx:protokollpaket --prune`, Zeitplan täglich).
 *
 * Quellen: Dateien `*.log` unter config('eduksx.logging.package.sources') (Vorgabe storage/logs). JSON-Zeilen werden
 * feldweise maskiert, andere Zeilen nach Mustern; Zeilen ohne Zeitangabe folgen der Zeile davor (Stacktrace).
 */
final class LogPackage
{
    public const FORMAT = 'eduksx-log-package/1';

    public const EXTENSION = '.eduksx-logs';

    /**
     * @return array{path: string, lines: int, bytes: int, sha256: string, security_event_id: string}
     */
    public static function create(CarbonImmutable $from, CarbonImmutable $to, Actor $actor, ?string $reason = null): array
    {
        $publicKey = self::publicKey();
        if ($to->lessThanOrEqualTo($from)) {
            throw new RuntimeException('Der Zeitraum ist leer («bis» liegt vor «von»).');
        }
        $maxDays = (int) config('eduksx.logging.package.max_days', 31);
        if ($from->diffInDays($to) > $maxDays) {
            throw new RuntimeException("Höchstens {$maxDays} Tage pro Paket.");
        }

        [$logs, $truncated] = self::collect($from->utc(), $to->utc());
        $content = [
            'format' => self::FORMAT,
            'module' => Module::key(),
            'version' => Module::version(),
            'created_at' => CarbonImmutable::now('UTC')->toIso8601ZuluString(),
            'period' => ['from' => $from->utc()->toIso8601ZuluString(), 'to' => $to->utc()->toIso8601ZuluString()],
            'manifest' => self::safely(fn (): array => Manifest::build()),
            'health' => self::safely(fn (): array => Health::report()),
            'environment' => ['php' => PHP_VERSION, 'laravel' => app()->version(), 'app_env' => (string) config('app.env')],
            'truncated' => $truncated,
            'logs' => $logs,
        ];
        $json = (string) json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $sealed = sodium_crypto_box_seal((string) gzencode($json, 9), $publicKey);

        $directory = self::directory();
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Ablage für Protokollpakete fehlt.');
        }
        $path = $directory.'/protokollpaket-'.Module::key().'-'.CarbonImmutable::now('UTC')->format('Ymd-His').self::EXTENSION;
        $handle = fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Protokollpaket konnte nicht geschrieben werden.');
        }
        chmod($path, 0600);
        fwrite($handle, $sealed);
        fclose($handle);

        $summary = ['lines' => count($logs), 'bytes' => strlen($sealed), 'sha256' => hash('sha256', $sealed)];
        $eventId = SecurityLog::record('logs.package_created', null, $summary + [
            'from' => $content['period']['from'], 'to' => $content['period']['to'], 'truncated' => $truncated,
        ], $reason, $actor);

        return ['path' => $path] + $summary + ['security_event_id' => $eventId];
    }

    /**
     * Für den Support: Paket mit dem Schlüsselpaar (base64, sodium_crypto_box_keypair) öffnen.
     *
     * @return array<string, mixed>
     */
    public static function open(string $sealed, string $keypair): array
    {
        $keypairRaw = base64_decode($keypair, true);
        if ($keypairRaw === false || strlen($keypairRaw) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES) {
            throw new RuntimeException('Schlüsselpaar ungültig.');
        }
        $gz = sodium_crypto_box_seal_open($sealed, $keypairRaw);
        if ($gz === false) {
            throw new RuntimeException('Paket lässt sich mit diesem Schlüssel nicht öffnen.');
        }
        $decoded = json_decode((string) gzdecode($gz), true);
        if (! is_array($decoded) || ($decoded['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Kein Protokollpaket.');
        }

        return $decoded;
    }

    /** @return array{public_key: string, keypair: string} base64; das Schlüsselpaar bleibt beim Support */
    public static function generateKeypair(): array
    {
        self::requireSodium();
        $keypair = sodium_crypto_box_keypair();

        return [
            'public_key' => base64_encode(sodium_crypto_box_publickey($keypair)),
            'keypair' => base64_encode($keypair),
        ];
    }

    /** Pakete älter als die Frist löschen (Vorgabe 30 Tage). @return int Anzahl */
    public static function prune(?int $days = null): int
    {
        $days ??= (int) config('eduksx.logging.package.retention_days', 30);
        $limit = CarbonImmutable::now()->subDays($days)->getTimestamp();
        $count = 0;
        foreach (glob(self::directory().'/*'.self::EXTENSION) ?: [] as $file) {
            if (filemtime($file) < $limit && unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    public static function directory(): string
    {
        return (string) (config('eduksx.logging.package.directory') ?? storage_path('app/eduksx/protokollpakete'));
    }

    /** @return array{0: list<string>, 1: bool} Zeilen (maskiert) und ob gekürzt wurde */
    private static function collect(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $mask = new MaskPersonalData((array) config('eduksx.logging.mask_keys', []));
        $maxBytes = (int) config('eduksx.logging.package.max_bytes', 20 * 1024 * 1024);
        /** @var list<string> $sources */
        $sources = (array) (config('eduksx.logging.package.sources') ?? [storage_path('logs')]);
        $files = [];
        foreach ($sources as $source) {
            $files = array_merge($files, is_dir($source) ? (glob(rtrim($source, '/').'/*.log') ?: []) : (is_file($source) ? [$source] : []));
        }
        sort($files);

        $lines = [];
        $bytes = 0;
        foreach ($files as $file) {
            // Dateien, die vor dem Zeitraum zuletzt geschrieben wurden, enthalten nichts daraus
            if (filemtime($file) < $from->getTimestamp()) {
                continue;
            }
            $handle = fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }
            $keep = false;
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue;
                }
                $time = self::timeOf($line);
                if ($time !== null) {
                    $keep = $time->greaterThanOrEqualTo($from) && $time->lessThan($to);
                }
                if (! $keep) {
                    continue;
                }
                $masked = self::maskLine($line, $mask);
                $bytes += strlen($masked);
                if ($bytes > $maxBytes) {
                    fclose($handle);

                    return [$lines, true];
                }
                $lines[] = $masked;
            }
            fclose($handle);
        }

        return [$lines, false];
    }

    private static function maskLine(string $line, MaskPersonalData $mask): string
    {
        if (str_starts_with($line, '{')) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                if (isset($decoded['message']) && is_string($decoded['message'])) {
                    $decoded['message'] = MaskPersonalData::maskText($decoded['message']);
                }

                return (string) json_encode($mask->maskArray($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return MaskPersonalData::maskText($line);
    }

    /** Zeit aus JSON (extra.time, datetime) oder Laravel-Zeile «[2026-11-26 10:00:00] …»; sonst null. */
    private static function timeOf(string $line): ?CarbonImmutable
    {
        try {
            if (str_starts_with($line, '{')) {
                $decoded = json_decode($line, true);
                $value = is_array($decoded) ? ($decoded['extra']['time'] ?? $decoded['datetime'] ?? $decoded['time'] ?? null) : null;

                return is_string($value) ? CarbonImmutable::parse($value)->utc() : null;
            }
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]/', $line, $m) === 1) {
                return CarbonImmutable::parse($m[1], (string) config('app.timezone', 'UTC'))->utc();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function publicKey(): string
    {
        self::requireSodium();
        $configured = config('eduksx.logging.package.public_key');
        $key = is_string($configured) ? base64_decode($configured, true) : false;
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new RuntimeException('Öffentlicher Schlüssel des Supports fehlt oder ist ungültig (EDUKSX_SUPPORT_PUBLIC_KEY).');
        }

        return $key;
    }

    private static function requireSodium(): void
    {
        if (! function_exists('sodium_crypto_box_seal')) {
            throw new RuntimeException('Die PHP-Erweiterung sodium fehlt.');
        }
    }

    /**
     * @param  \Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private static function safely(\Closure $build): array
    {
        try {
            return $build();
        } catch (Throwable $e) {
            return ['error' => $e::class];
        }
    }
}
