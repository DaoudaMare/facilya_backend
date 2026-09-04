<?php

namespace App\Support;

class Phone
{
    public static function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '00226')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '226')) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '226')) {
            $digits = substr($digits, 3);
        }

        // SMS Orange : 7684843 (0 local omis) → 07684843
        if (strlen($digits) === 7) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    public static function format(string $value): string
    {
        $digits = self::normalize($value);

        if (strlen($digits) !== 8) {
            return $digits;
        }

        return trim(chunk_split($digits, 2, ' '));
    }

    public static function isValid(string $value): bool
    {
        $digits = self::normalize($value);

        return (bool) preg_match('/^\d{8,10}$/', $digits);
    }

    public static function matches(string $left, string $right): bool
    {
        $a = self::normalize($left);
        $b = self::normalize($right);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        if (strlen($a) >= 8 && strlen($b) >= 8 && substr($a, -8) === substr($b, -8)) {
            return true;
        }

        return strlen($a) >= 7
            && strlen($b) >= 7
            && substr($a, -7) === substr($b, -7);
    }

    /**
     * Chatid Zapwize = numéro du destinataire, chiffres uniquement.
     * On préfixe 226 s’il manque, sans retirer le 0 local.
     * Ex. 07684843 → 22607684843 (comme le curl qui fonctionne).
     */
    public static function toWhatsAppChatId(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '00226')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return '';
        }

        if (! str_starts_with($digits, '226')) {
            $digits = '226'.$digits;
        }

        return $digits;
    }
}
