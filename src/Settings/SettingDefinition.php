<?php

namespace EduKSX\Core\Settings;

use Closure;
use InvalidArgumentException;

/**
 * Eine Einstellung eines Moduls (EduKSX-Architektur KONZEPT-STACK ST5). Vorbild: Glossary `App\Support\Settings`.
 *
 * Typen: `bool` (auch Feature-Schalter), `int`, `choice`, `text`, `email`, `url`, `secret`.
 * `env`: Umgebungsvariable mit Vorrang – ist sie gesetzt, ist die Einstellung in der Administration gesperrt.
 * `default`: fester Wert oder Closure (z. B. aus der Konfiguration des Moduls).
 */
final class SettingDefinition
{
    public const TYPES = ['bool', 'int', 'choice', 'text', 'email', 'url', 'secret'];

    /**
     * @param  array<string, string>  $options  Auswahl für `choice`: Wert → Bezeichnung
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $label,
        public readonly mixed $default = null,
        public readonly string $group = '',
        public readonly string $hint = '',
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $unit = null,
        public readonly array $options = [],
        public readonly ?string $env = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/', $key) !== 1) {
            throw new InvalidArgumentException("Ungültiger Schlüssel «{$key}» (Kleinbuchstaben, Ziffern, _ und Punkte).");
        }
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unbekannter Typ «{$type}» für «{$key}».");
        }
        if ($type === 'choice' && $options === []) {
            throw new InvalidArgumentException("«{$key}»: Typ choice braucht Auswahl (options).");
        }
    }

    /**
     * Aus einem Array (z. B. in einem ServiceProvider oder in der Konfiguration).
     *
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(string $key, array $definition): self
    {
        /** @var array<string, string> $options */
        $options = $definition['options'] ?? [];

        return new self(
            key: $key,
            type: (string) ($definition['type'] ?? ''),
            label: (string) ($definition['label'] ?? $key),
            default: $definition['default'] ?? null,
            group: (string) ($definition['group'] ?? ''),
            hint: (string) ($definition['hint'] ?? ''),
            min: isset($definition['min']) ? (int) $definition['min'] : null,
            max: isset($definition['max']) ? (int) $definition['max'] : null,
            unit: isset($definition['unit']) ? (string) $definition['unit'] : null,
            options: $options,
            env: isset($definition['env']) ? (string) $definition['env'] : null,
        );
    }

    /** Vorgabe, Closures ausgewertet; Geheimnisse ohne Vorgabe sind leer. */
    public function defaultValue(): mixed
    {
        $value = $this->default instanceof Closure ? ($this->default)() : $this->default;
        if ($value === null) {
            return match ($this->type) {
                'bool' => false,
                'int' => $this->min ?? 0,
                default => '',
            };
        }

        return $this->cast($value);
    }

    /** Wert aus der Umgebung in den Typ der Einstellung umwandeln. */
    public function cast(mixed $value): mixed
    {
        return match ($this->type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            default => (string) $value,
        };
    }

    /** Prüft einen Wert aus der Administration; ungültig → InvalidArgumentException mit deutscher Meldung. */
    public function validate(mixed $value): mixed
    {
        $label = $this->label;

        switch ($this->type) {
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'int':
                $min = $this->min ?? PHP_INT_MIN;
                $max = $this->max ?? PHP_INT_MAX;
                if (! is_numeric($value) || (int) $value != $value || (int) $value < $min || (int) $value > $max) {
                    $range = $this->min !== null && $this->max !== null ? " von {$this->min} bis {$this->max}" : '';
                    throw new InvalidArgumentException("«{$label}»: ganze Zahl{$range}.");
                }

                return (int) $value;
            case 'choice':
                $v = (string) $value;
                if (! array_key_exists($v, $this->options)) {
                    throw new InvalidArgumentException("«{$label}»: ungültige Auswahl.");
                }

                return $v;
            case 'email':
                $v = trim((string) $value);
                if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
                    throw new InvalidArgumentException("«{$label}»: gültige E-Mail-Adresse angeben.");
                }

                return $v;
            case 'url':
                $v = trim((string) $value);
                if ($v !== '' && preg_match('#^https://\S+$#i', $v) !== 1) {
                    throw new InvalidArgumentException("«{$label}»: Adresse mit https://.");
                }

                return $v;
            default: // text, secret
                $v = $this->type === 'secret' ? (string) $value : trim((string) $value);
                $max = $this->max ?? ($this->type === 'secret' ? 4000 : 200);
                if (mb_strlen($v) > $max) {
                    throw new InvalidArgumentException("«{$label}»: höchstens {$max} Zeichen.");
                }

                return $v;
        }
    }
}
