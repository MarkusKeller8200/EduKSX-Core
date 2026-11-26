<?php

namespace EduKSX\Core\Console;

use Carbon\CarbonImmutable;
use EduKSX\Core\Data\Actor;
use EduKSX\Core\Logging\LogPackage;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/** Protokollpaket für den Support (KONZEPT-PROTOKOLLIERUNG §9): maskiert, verschlüsselt, im Sicherheitsprotokoll. */
final class LogPackageCommand extends Command
{
    protected $signature = 'eduksx:protokollpaket
        {--from= : Beginn (z. B. "2026-11-26 08:00"; Vorgabe: vor 24 Stunden)}
        {--to= : Ende (Vorgabe: jetzt)}
        {--reason= : Fall bzw. Grund (steht im Sicherheitsprotokoll)}
        {--prune : Pakete älter als 30 Tage löschen (Zeitplan)}
        {--keypair : Schlüsselpaar für den Support erzeugen}';

    protected $description = 'Protokollpaket für den Support erzeugen (maskiert und verschlüsselt)';

    public function handle(): int
    {
        if ($this->option('keypair')) {
            $keys = LogPackage::generateKeypair();
            $this->line('EDUKSX_SUPPORT_PUBLIC_KEY='.$keys['public_key']);
            $this->warn('Schlüsselpaar nur beim Support aufbewahren, nie in eine Instanz eintragen:');
            $this->line($keys['keypair']);

            return self::SUCCESS;
        }
        if ($this->option('prune')) {
            $this->line(LogPackage::prune().' Paket(e) gelöscht.');

            return self::SUCCESS;
        }

        try {
            $to = $this->option('to') !== null ? CarbonImmutable::parse((string) $this->option('to')) : CarbonImmutable::now();
            $from = $this->option('from') !== null ? CarbonImmutable::parse((string) $this->option('from')) : $to->subDay();
            $result = LogPackage::create($from, $to, new Actor('console'), $this->option('reason') !== null ? (string) $this->option('reason') : null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Zeitraum nicht lesbar (z. B. "2026-11-26 08:00").');

            return self::INVALID;
        }

        $this->info('Protokollpaket: '.$result['path']);
        $this->line("{$result['lines']} Zeilen, {$result['bytes']} Bytes, SHA-256 {$result['sha256']}");
        $this->line('Die Datei selbst an den Support senden; sie wird nach spätestens 30 Tagen gelöscht.');

        return self::SUCCESS;
    }
}
