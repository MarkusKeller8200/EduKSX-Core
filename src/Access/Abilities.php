<?php

namespace EduKSX\Core\Access;

use Closure;
use EduKSX\Core\Module;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Rechte und Rollen eines Moduls (EduKSX-Architektur KONZEPT-ROLLEN RO1, RO2; Vorbild Glossary `App\Access\Permission`).
 * Rollen bündeln Rechte; geprüft wird immer das **Recht** (Gate), nie der Rollenname. Effektive Rechte sind die
 * Vereinigung aller Rollen einer Person.
 *
 * In config/eduksx.php des Moduls:
 *
 *     'access' => [
 *         'abilities' => [
 *             'terms.read'    => ['scope_action' => 'read',  'label' => 'Begriffe lesen'],
 *             'terms.write'   => ['scope_action' => 'write', 'label' => 'Begriffe bearbeiten'],
 *             'terms.publish' => ['scope_action' => 'write', 'label' => 'Veröffentlichen', 'class' => 'privileged'],
 *         ],
 *         'roles' => [
 *             'reader' => ['terms.read'],
 *             'editor' => ['reader', 'terms.write'],     // Rollenname = alle Rechte dieser Rolle
 *             'admin'  => ['editor', 'terms.publish'],
 *         ],
 *     ],
 *
 * Rechte prüfen: Gates registrieren mit der Quelle der Rollen (Datenbank des Moduls oder Claim `eduksx_roles`):
 *
 *     Abilities::registerGates(fn ($user) => $user->roleNames());
 *     Gate::allows('terms.publish');
 *
 * Schutzklassen wie im Glossary: `normal`, `privileged` (zweiter Faktor, nur innerhalb der eigenen Grenze vergebbar),
 * `system` (nur Super-Admin). Geltungsbereiche (`unit`, `group`, `self`) und `*_sensitive` folgen nach API G3, sobald
 * ein Modul sie braucht (KONZEPT-ROLLEN §5).
 */
final class Abilities
{
    public const PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    public const ROLE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public const SCOPE_ACTIONS = ['read', 'write', 'delete', 'admin'];

    public const CLASSES = ['normal', 'privileged', 'system'];

    /** Standardrollen jedes Moduls (RO1), in aufsteigender Reihenfolge. */
    public const STANDARD_ROLES = ['reader', 'editor', 'admin'];

    /** @return array<string, array{scope_action: string, label: string, class: string, sensitive: bool}> */
    public static function all(): array
    {
        $abilities = [];
        /** @var array<string, array<string, mixed>> $config */
        $config = (array) config('eduksx.access.abilities', []);
        foreach ($config as $name => $definition) {
            if (preg_match(self::PATTERN, (string) $name) !== 1) {
                throw new InvalidArgumentException("Recht «{$name}»: Schema <ressource>.<aktion> (RO2).");
            }
            $action = (string) ($definition['scope_action'] ?? '');
            if (! in_array($action, self::SCOPE_ACTIONS, true)) {
                throw new InvalidArgumentException("Recht «{$name}»: scope_action muss read, write, delete oder admin sein.");
            }
            $class = (string) ($definition['class'] ?? ($action === 'admin' ? 'privileged' : 'normal'));
            if (! in_array($class, self::CLASSES, true)) {
                throw new InvalidArgumentException("Recht «{$name}»: Schutzklasse normal, privileged oder system.");
            }
            $abilities[(string) $name] = [
                'scope_action' => $action,
                'label' => (string) ($definition['label'] ?? $name),
                'class' => $class,
                'sensitive' => (bool) ($definition['sensitive'] ?? false),
            ];
        }

        return $abilities;
    }

    /** @return array<string, list<string>> Rolle → Rechte (aufgelöst, sortiert) */
    public static function roles(): array
    {
        /** @var array<string, list<string>> $config */
        $config = (array) config('eduksx.access.roles', []);
        $resolved = [];
        foreach (array_keys($config) as $role) {
            $resolved[(string) $role] = self::resolve((string) $role, $config, []);
        }

        return $resolved;
    }

    /**
     * Effektive Rechte für Rollen (Vereinigung). Unbekannte Rollen geben keine Rechte.
     *
     * @param  iterable<string>  $roles
     * @return list<string>
     */
    public static function forRoles(iterable $roles): array
    {
        $map = self::roles();
        $abilities = [];
        foreach ($roles as $role) {
            foreach ($map[$role] ?? [] as $ability) {
                $abilities[$ability] = true;
            }
        }
        $list = array_keys($abilities);
        sort($list);

        return $list;
    }

    /** Scope, den ein Token für dieses Recht mindestens braucht: `<modul>:<ressource>.<scope-aktion>` (API C2). */
    public static function scopeFor(string $ability): string
    {
        $definition = self::all()[$ability] ?? throw new InvalidArgumentException("Unbekanntes Recht «{$ability}».");

        return Module::key().':'.explode('.', $ability)[0].'.'.$definition['scope_action'];
    }

    /**
     * Gates für alle Rechte: erlaubt, wenn eine Rolle der Person das Recht enthält. Der Scope eines Tokens begrenzt
     * zusätzlich (`$scopesOf` liefert die Scopes des aktuellen Tokens oder null bei einer Sitzung).
     *
     * @param  Closure(mixed): iterable<string>  $rolesOf
     * @param  (Closure(mixed): (iterable<string>|null))|null  $scopesOf
     */
    public static function registerGates(Closure $rolesOf, ?Closure $scopesOf = null): void
    {
        foreach (array_keys(self::all()) as $ability) {
            Gate::define($ability, function (mixed $user) use ($ability, $rolesOf, $scopesOf): bool {
                if (! in_array($ability, self::forRoles($rolesOf($user)), true)) {
                    return false;
                }
                $scopes = $scopesOf !== null ? $scopesOf($user) : null;
                if ($scopes === null) {
                    return true;
                }
                $needed = self::scopeFor($ability);
                foreach ($scopes as $scope) {
                    if ($scope === $needed) {
                        return true;
                    }
                }

                return false;
            });
        }
    }

    /** @return list<array{name: string, scope_action: string, sensitive?: bool}> für den Steckbrief (S5) */
    public static function manifest(): array
    {
        $list = [];
        foreach (self::all() as $name => $definition) {
            $entry = ['name' => $name, 'scope_action' => $definition['scope_action']];
            if ($definition['sensitive']) {
                $entry['sensitive'] = true;
            }
            $list[] = $entry;
        }

        return $list;
    }

    /**
     * Regeln aus KONZEPT-ROLLEN prüfen; leer, wenn alles stimmt.
     *
     * @return list<string>
     */
    public static function problems(): array
    {
        $problems = [];
        try {
            $abilities = self::all();
            $roles = self::roles();
        } catch (InvalidArgumentException $e) {
            return [$e->getMessage()];
        }
        foreach (self::STANDARD_ROLES as $role) {
            if (! isset($roles[$role])) {
                $problems[] = "Standardrolle «{$role}» fehlt (RO1).";
            }
        }
        foreach ($roles as $role => $granted) {
            if (preg_match(self::ROLE_PATTERN, $role) !== 1) {
                $problems[] = "Rolle «{$role}»: Name nach Steckbrief-Schema (Kleinbuchstaben, Ziffern, _).";
            }
            foreach ($granted as $ability) {
                if (! isset($abilities[$ability])) {
                    $problems[] = "Rolle «{$role}»: unbekanntes Recht «{$ability}».";
                } elseif ($abilities[$ability]['class'] === 'system') {
                    $problems[] = "Rolle «{$role}»: Recht «{$ability}» ist «system» und gehört nur dem Super-Admin.";
                }
            }
        }
        // reader ⊆ editor ⊆ admin
        for ($i = 1; $i < count(self::STANDARD_ROLES); $i++) {
            [$lower, $upper] = [self::STANDARD_ROLES[$i - 1], self::STANDARD_ROLES[$i]];
            if (isset($roles[$lower], $roles[$upper]) && array_diff($roles[$lower], $roles[$upper]) !== []) {
                $problems[] = "Rolle «{$upper}» hat nicht alle Rechte von «{$lower}» (RO1).";
            }
        }
        foreach ($abilities as $name => $definition) {
            if ($definition['scope_action'] === 'read' && ! $definition['sensitive'] && isset($roles['reader']) && ! in_array($name, $roles['reader'], true) && str_ends_with($name, '.read')) {
                $problems[] = "Rolle «reader» fehlt «{$name}» (RO1: alle *.read ausser besonders schützenswerten).";
            }
            if ($definition['sensitive'] && array_intersect(['reader', 'editor'], array_keys(array_filter($roles, fn (array $g): bool => in_array($name, $g, true)))) !== []) {
                $problems[] = "«{$name}» ist besonders schützenswert: nicht in reader oder editor (RO3).";
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, list<string>>  $config
     * @param  list<string>  $path
     * @return list<string>
     */
    private static function resolve(string $role, array $config, array $path): array
    {
        if (in_array($role, $path, true)) {
            throw new InvalidArgumentException('Rollen verweisen im Kreis: '.implode(' → ', [...$path, $role]).'.');
        }
        $abilities = [];
        foreach ((array) ($config[$role] ?? []) as $entry) {
            $entry = (string) $entry;
            if (isset($config[$entry])) {
                foreach (self::resolve($entry, $config, [...$path, $role]) as $ability) {
                    $abilities[$ability] = true;
                }
            } else {
                $abilities[$entry] = true;
            }
        }
        $list = array_keys($abilities);
        sort($list);

        return $list;
    }
}
