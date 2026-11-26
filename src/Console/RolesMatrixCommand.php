<?php

namespace EduKSX\Core\Console;

use EduKSX\Core\Access\Abilities;
use EduKSX\Core\Access\AbilityMatrix;
use Illuminate\Console\Command;

/** Rollen-Rechte-Matrix erzeugen bzw. prüfen (KONZEPT-ROLLEN §8). */
final class RolesMatrixCommand extends Command
{
    protected $signature = 'eduksx:rollen-rechte
        {--check : nur prüfen, ob die Datei zum Code passt (CI)}
        {--path= : Datei (Vorgabe docs/ROLLEN-RECHTE.md)}';

    protected $description = 'Rollen-Rechte-Matrix docs/ROLLEN-RECHTE.md aus dem Code erzeugen oder prüfen';

    public function handle(): int
    {
        $problems = Abilities::problems();
        foreach ($problems as $problem) {
            $this->error($problem);
        }
        if ($problems !== []) {
            return self::FAILURE;
        }

        $option = (string) ($this->option('path') ?? AbilityMatrix::DEFAULT_PATH);
        $path = str_starts_with($option, '/') ? $option : base_path($option);
        $markdown = AbilityMatrix::markdown();
        if ($this->option('check')) {
            if (! is_file($path) || file_get_contents($path) !== $markdown) {
                $this->error('Rollen-Rechte-Matrix ist veraltet: php artisan eduksx:rollen-rechte');

                return self::FAILURE;
            }
            $this->info('Rollen-Rechte-Matrix ist aktuell.');

            return self::SUCCESS;
        }
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $markdown);
        $this->info('Geschrieben: '.$path);

        return self::SUCCESS;
    }
}
