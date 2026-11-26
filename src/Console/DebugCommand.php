<?php

namespace EduKSX\Core\Console;

use EduKSX\Core\Data\Actor;
use EduKSX\Core\Logging\OrganizationDebug;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Befristetes Debug je Organisation auf dem Server (KONZEPT-PROTOKOLLIERUNG §5). Wer die Konsole des Servers hat,
 * handelt als Super-Admin; Ein, Aus und Ablauf stehen im Sicherheitsprotokoll.
 */
final class DebugCommand extends Command
{
    protected $signature = 'eduksx:debug
        {organization? : ID der Organisation (UUID)}
        {--minutes=60 : Dauer, höchstens 60 Minuten}
        {--reason= : Grund (Pflicht, steht im Sicherheitsprotokoll)}
        {--off : Debug vorzeitig ausschalten}
        {--expire : abgelaufene Sitzungen beenden (Zeitplan)}';

    protected $description = 'Befristetes Debug je Organisation ein- oder ausschalten (höchstens 60 Minuten)';

    public function handle(): int
    {
        if ($this->option('expire')) {
            $expired = OrganizationDebug::expire();
            $this->line(count($expired).' Sitzung(en) beendet.');

            return self::SUCCESS;
        }

        $organization = $this->argument('organization');
        if (! is_string($organization) || $organization === '') {
            foreach (OrganizationDebug::sessions() as $id => $until) {
                $this->line("{$id}  bis {$until}");
            }
            if (OrganizationDebug::sessions() === []) {
                $this->line('Kein Debug aktiv.');
            }

            return self::SUCCESS;
        }

        $reason = trim((string) $this->option('reason'));
        $actor = new Actor('console');
        try {
            if ($this->option('off')) {
                $done = OrganizationDebug::disable($organization, $reason !== '' ? $reason : 'vorzeitig ausgeschaltet', $actor);
                $this->line($done ? 'Debug ausgeschaltet.' : 'Für diese Organisation lief kein Debug.');

                return self::SUCCESS;
            }
            $until = OrganizationDebug::enable($organization, (int) $this->option('minutes'), $reason, $actor);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }
        $this->info('Debug bis '.$until->toIso8601ZuluString().' (UTC). Maskierung bleibt aktiv.');

        return self::SUCCESS;
    }
}
