<?php

namespace EduKSX\Core\Settings;

use EduKSX\Core\Data\DataChanges;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Einstellungen und Feature-Schalter eines Moduls (EduKSX-Architektur KONZEPT-STACK ST5), herausgelöst aus dem
 * Glossary (`App\Support\Settings`, #106, #366).
 *
 * - Vorrang: Umgebungsvariable (sperrt die Einstellung) → Wert aus der Administration → Vorgabe.
 * - Gespeichert werden nur Abweichungen von der Vorgabe (Tabelle `settings`); Geheimnisse verschlüsselt (APP_KEY).
 * - Jede Änderung steht in der Historie `data_changes` (DB-Standard A7); Geheimnisse nur als «gesetzt/entfernt».
 * - Feature-Schalter sind Einstellungen vom Typ `bool`: `Settings::enabled('mcp.enabled')`.
 *
 * Definitionen legt das Modul fest – in `config/eduksx.php` unter `settings.definitions` oder im ServiceProvider:
 *
 *     app(Settings::class)->define('mcp.enabled', ['type' => 'bool', 'label' => 'MCP-Server', 'env' => 'APP_MCP']);
 *
 * Wer ändern darf, prüft das Modul (Recht nach KONZEPT-ROLLEN, z. B. `settings.admin`).
 */
final class Settings
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    /** @var array<string, mixed>|null Zwischenspeicher der gespeicherten Werte (pro Anfrage). */
    private ?array $stored = null;

    public function __construct(private readonly string $table = 'settings')
    {
        /** @var array<string, array<string, mixed>> $fromConfig */
        $fromConfig = (array) config('eduksx.settings.definitions', []);
        foreach ($fromConfig as $key => $definition) {
            $this->define($key, $definition);
        }
    }

    /** @param  array<string, mixed>|SettingDefinition  $definition */
    public function define(string $key, array|SettingDefinition $definition): void
    {
        $this->definitions[$key] = $definition instanceof SettingDefinition
            ? $definition
            : SettingDefinition::fromArray($key, $definition);
    }

    /** @return array<string, SettingDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    public function definition(string $key): SettingDefinition
    {
        return $this->definitions[$key] ?? throw new InvalidArgumentException("Unbekannte Einstellung «{$key}».");
    }

    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $env = $this->environmentValue($definition);
        if ($env !== null) {
            return $definition->cast($env);
        }
        $stored = $this->stored();
        if (! array_key_exists($key, $stored)) {
            return $definition->defaultValue();
        }
        if ($definition->type === 'secret') {
            try {
                return Crypt::decryptString((string) $stored[$key]);
            } catch (DecryptException) {
                return '';   // anderer APP_KEY: neu setzen
            }
        }

        return $stored[$key];
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /** Feature-Schalter: Einstellung vom Typ bool. */
    public function enabled(string $key): bool
    {
        if ($this->definition($key)->type !== 'bool') {
            throw new InvalidArgumentException("«{$key}» ist kein Schalter (Typ bool).");
        }

        return $this->bool($key);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }

    /** Name der Umgebungsvariable, wenn sie gesetzt ist und die Einstellung sperrt; sonst null. */
    public function locked(string $key): ?string
    {
        $definition = $this->definition($key);

        return $this->environmentValue($definition) !== null ? $definition->env : null;
    }

    public function source(string $key): SettingSource
    {
        if ($this->locked($key) !== null) {
            return SettingSource::Environment;
        }

        return array_key_exists($key, $this->stored()) ? SettingSource::Administration : SettingSource::Default;
    }

    /**
     * Mehrere Werte setzen; gleich der Vorgabe = Eintrag entfernen. Ein ungültiger Wert → Ausnahme, nichts gespeichert.
     * Gesperrte Werte und Geheimnisse werden übersprungen (Geheimnisse nur über setSecret()).
     *
     * @param  array<string, mixed>  $values
     * @return list<string> geänderte Schlüssel
     */
    public function set(array $values, ?string $actorId = null): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            $definition = $this->definition($key);
            if ($definition->type === 'secret' || $this->locked($key) !== null) {
                continue;
            }
            $clean[$key] = $definition->validate($value);
        }

        $changed = [];
        DB::transaction(function () use ($clean, $actorId, &$changed): void {
            foreach ($clean as $key => $value) {
                $old = $this->get($key);
                if ($old === $value) {
                    continue;
                }
                if ($value === $this->definition($key)->defaultValue()) {
                    DB::table($this->table)->where('key', $key)->delete();
                } else {
                    DB::table($this->table)->updateOrInsert(['key' => $key], [
                        'value' => json_encode($value), 'updated_by_id' => $actorId, 'updated_at' => now(),
                    ]);
                }
                DataChanges::write($this->table, $key, 'updated', ['value' => ['old' => $old, 'new' => $value]]);
                $changed[] = $key;
            }
        });
        $this->flush();

        return $changed;
    }

    /** Geheimnis setzen oder entfernen (null bzw. leer); in der Historie nur «gesetzt», «erneuert» oder «entfernt». */
    public function setSecret(string $key, ?string $value, ?string $actorId = null): void
    {
        $definition = $this->definition($key);
        if ($definition->type !== 'secret') {
            throw new InvalidArgumentException("«{$key}» ist kein Geheimnis.");
        }
        if ($this->locked($key) !== null) {
            throw new InvalidArgumentException('Wird in der Umgebung gesetzt ('.$this->locked($key).').');
        }
        $had = array_key_exists($key, $this->stored());
        $remove = $value === null || $value === '';
        if (! $remove) {
            $definition->validate($value);
        }
        DB::transaction(function () use ($key, $value, $had, $remove, $actorId): void {
            if ($remove) {
                DB::table($this->table)->where('key', $key)->delete();
            } else {
                DB::table($this->table)->updateOrInsert(['key' => $key], [
                    'value' => json_encode(Crypt::encryptString((string) $value)), 'updated_by_id' => $actorId, 'updated_at' => now(),
                ]);
            }
            DataChanges::write($this->table, $key, 'updated', ['value' => [
                'old' => $had ? 'gesetzt' : null,
                'new' => $remove ? 'entfernt' : ($had ? 'erneuert' : 'gesetzt'),
            ]]);
        });
        $this->flush();
    }

    /** Zwischenspeicher leeren (nach Änderungen ausserhalb dieses Dienstes, in Tests). */
    public function flush(): void
    {
        $this->stored = null;
    }

    private function environmentValue(SettingDefinition $definition): ?string
    {
        if ($definition->env === null) {
            return null;
        }
        $value = $_ENV[$definition->env] ?? $_SERVER[$definition->env] ?? getenv($definition->env);
        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        if ($this->stored !== null) {
            return $this->stored;
        }
        // vor der Migration (erster Start) gelten die Vorgaben
        try {
            $this->stored = DB::table($this->table)->pluck('value', 'key')
                ->map(fn (mixed $v): mixed => json_decode((string) $v, true))->all();
        } catch (QueryException) {
            return [];
        }

        return $this->stored;
    }
}
