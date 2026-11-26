<?php

namespace EduKSX\Core\Console;

use EduKSX\Core\Security\SecurityLog;
use Illuminate\Console\Command;

/** Sicherheitsprotokoll prüfen und Prüfpunkt ins Protokoll schreiben (DB-Standard A7); --prune löscht nach Frist. */
final class SecurityLogCommand extends Command
{
    protected $signature = 'eduksx:sicherheitsprotokoll {--prune : Einträge älter als die Frist (1 Jahr) löschen}';

    protected $description = 'Hash-Kette des Sicherheitsprotokolls prüfen und Prüfpunkt schreiben';

    public function handle(): int
    {
        if (! SecurityLog::installed()) {
            $this->warn('Tabelle fehlt: php artisan vendor:publish --tag=eduksx-security-migrations');

            return self::SUCCESS;
        }
        if ($this->option('prune')) {
            $this->line(SecurityLog::prune().' Eintrag/Einträge gelöscht.');
        }
        $result = SecurityLog::checkpoint();
        if (! $result['valid']) {
            $this->error("Kette gebrochen bei Eintrag {$result['broken_at']}.");

            return self::FAILURE;
        }
        $this->info("{$result['entries']} Einträge, Kette gültig.");
        $this->line('Prüfpunkt: '.($result['last_hash'] ?? '–'));

        return self::SUCCESS;
    }
}
