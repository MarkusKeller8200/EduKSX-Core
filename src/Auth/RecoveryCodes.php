<?php

namespace EduKSX\Core\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Wiederherstellungscodes für den zweiten Faktor (KONZEPT-ANMELDUNG §3): 8 einmalige Codes, gespeichert nur als Hash.
 * Herausgelöst aus dem Glossary (`App\Glossary\TwoFactor`), Format unverändert: `ABCDE-FGHIJ`, Bindestrich optional.
 */
final class RecoveryCodes
{
    public const COUNT = 8;

    /** @return array{plain: list<string>, hashes: list<string>} Klartext einmal anzeigen, Hashes speichern */
    public static function generate(int $count = self::COUNT): array
    {
        $plain = [];
        for ($i = 0; $i < $count; $i++) {
            $plain[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }

        return ['plain' => $plain, 'hashes' => array_map(fn (string $c): string => Hash::make(self::normalize($c)), $plain)];
    }

    /**
     * Code einlösen: übrige Hashes zurück (Code verbraucht) oder null, wenn er nicht passt.
     *
     * @param  list<string>  $hashes
     * @return list<string>|null
     */
    public static function use(string $code, array $hashes): ?array
    {
        $code = self::normalize($code);
        if (preg_match('/^[A-Z0-9]{10}$/', $code) !== 1) {
            return null;
        }
        foreach ($hashes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashes[$index]);

                return array_values($hashes);
            }
        }

        return null;
    }

    private static function normalize(string $code): string
    {
        return strtoupper(str_replace(['-', ' '], '', trim($code)));
    }
}
