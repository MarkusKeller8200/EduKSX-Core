<?php

namespace EduKSX\Core\Access;

use EduKSX\Core\Module;

/**
 * Rollen-Rechte-Matrix je Modul als Markdown (KONZEPT-ROLLEN §8): Nachweis für das ISMS der Schule, aus dem Code
 * erzeugt mit `php artisan eduksx:rollen-rechte`; `--check` in der CI vergleicht Datei und Code.
 */
final class AbilityMatrix
{
    public const DEFAULT_PATH = 'docs/ROLLEN-RECHTE.md';

    public static function markdown(): string
    {
        $abilities = Abilities::all();
        $roles = Abilities::roles();
        $order = array_merge(
            array_values(array_intersect(Abilities::STANDARD_ROLES, array_keys($roles))),
            array_values(array_diff(array_keys($roles), Abilities::STANDARD_ROLES)),
        );
        ksort($abilities);

        $lines = [
            '# Rollen und Rechte – '.Module::key(),
            '',
            '> Erzeugt mit `php artisan eduksx:rollen-rechte` aus `config/eduksx.php` (`access`) – nicht von Hand ändern.',
            '> Regeln: EduKSX-Architektur KONZEPT-ROLLEN (RO1–RO3). Geprüft wird immer das Recht, nie der Rollenname.',
            '',
            '| Recht | Bezeichnung | Scope | Schutzklasse | '.implode(' | ', array_map(fn (string $r): string => "`{$r}`", $order)).' | Super-Admin |',
            '| --- | --- | --- | --- | '.implode(' | ', array_fill(0, count($order), ':---:')).' | :---: |',
        ];
        foreach ($abilities as $name => $definition) {
            $cells = [
                "`{$name}`",
                str_replace('|', '\\|', $definition['label']),
                '`'.Module::key().':'.explode('.', $name)[0].'.'.$definition['scope_action'].'`',
                $definition['class'].($definition['sensitive'] ? ', besonders schützenswert' : ''),
            ];
            foreach ($order as $role) {
                $cells[] = in_array($name, $roles[$role], true) ? '✓' : '';
            }
            $cells[] = '✓';
            $lines[] = '| '.implode(' | ', $cells).' |';
        }
        $lines[] = '';
        $lines[] = 'Schutzklassen: `normal`; `privileged` verlangt den zweiten Faktor; `system` nur Super-Admin.';

        return implode("\n", $lines)."\n";
    }
}
