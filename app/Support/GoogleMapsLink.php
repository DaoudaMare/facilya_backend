<?php

namespace App\Support;

class GoogleMapsLink
{
    public static function normalize(string $value): string
    {
        return trim($value);
    }

    public static function isValid(string $value): bool
    {
        $url = self::normalize($value);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (in_array($host, ['maps.app.goo.gl', 'goo.gl'], true)) {
            return $host !== 'goo.gl' || str_starts_with($path, '/maps');
        }

        if ($host === 'maps.google.com' || str_starts_with($host, 'maps.google.')) {
            return true;
        }

        if ($host === 'www.google.com' || $host === 'google.com' || preg_match('/^www\.google\.[a-z.]+$/', $host) === 1) {
            return str_contains($path, '/maps');
        }

        return false;
    }

    public static function validationMessage(): string
    {
        return 'Utilisez un lien Google Maps valide (maps.app.goo.gl ou google.com/maps).';
    }
}
