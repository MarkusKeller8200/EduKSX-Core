<?php

namespace EduKSX\Core\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Maskiert Personendaten und Geheimnisse in Protokollen (KONZEPT-PROTOKOLLIERUNG §6, API-Standard C7):
 * Werte unter bekannten Feldnamen ganz, in Texten bekannte Muster (E-Mail, Telefonnummer, AHV-Nummer, IBAN, Bearer-
 * Token, JWT). Module protokollieren IDs, keine Namen oder Inhalte – die Maskierung ist das Sicherheitsnetz.
 * Weitere Feldnamen: config('eduksx.logging.mask_keys').
 */
final class MaskPersonalData implements ProcessorInterface
{
    public const MASK = '[maskiert]';

    /** Feldnamen (Kleinschreibung, ohne _ und -), deren Wert immer maskiert wird. Die Quell-Adresse bleibt (API C7). */
    private const KEYS = [
        'email', 'mail', 'name', 'firstname', 'lastname', 'displayname', 'username', 'phone', 'mobile', 'telefon',
        'address', 'adresse', 'street', 'birthdate', 'dateofbirth', 'geburtsdatum', 'ahv', 'iban', 'password',
        'passwort', 'secret', 'token', 'accesstoken', 'refreshtoken', 'apikey', 'authorization', 'cookie', 'body',
        'clientsecret', 'otp', 'totp',
    ];

    /** @var array<string, string> Muster → Ersatz */
    private const PATTERNS = [
        // Bearer-Token und JWT
        '/\bBearer\s+[A-Za-z0-9._~+\/=-]{8,}/i' => 'Bearer '.self::MASK,
        '/\beyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}/' => self::MASK,
        // E-Mail
        '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/' => self::MASK,
        // AHV-Nummer (756.xxxx.xxxx.xx)
        '/\b756[.\s]?\d{4}[.\s]?\d{4}[.\s]?\d{2}\b/' => self::MASK,
        // IBAN (CH und andere)
        '/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){3,7}(?:\s?[A-Z0-9]{1,4})?\b/' => self::MASK,
        // Telefonnummern (international oder 0xx xxx xx xx)
        '/(?<![\w.-])(?:\+|00)\d{2}[\s.\/-]?\d{2,3}(?:[\s.\/-]?\d{2,4}){2,3}\b/' => self::MASK,
        '/(?<![\w.-])0\d{2}[\s.\/-]\d{3}[\s.\/-]\d{2}[\s.\/-]\d{2}\b/' => self::MASK,
    ];

    /** @var list<string> */
    private array $keys;

    /** @param  list<string>  $extraKeys */
    public function __construct(array $extraKeys = [])
    {
        $this->keys = array_values(array_unique(array_merge(self::KEYS, array_map(self::normalize(...), $extraKeys))));
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::maskText($record->message),
            context: $this->maskArray($record->context),
            extra: $this->maskArray($record->extra),
        );
    }

    public static function maskText(string $text): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(self::normalize($key), $this->keys, true) && $value !== null && $value !== '') {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            } elseif (is_string($value)) {
                $data[$key] = self::maskText($value);
            }
        }

        return $data;
    }

    private static function normalize(string $key): string
    {
        return str_replace(['_', '-', ' '], '', strtolower($key));
    }
}
