<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsExtractor
{
    /**
     * @return array{latitude: float, longitude: float, url: string}|null
     */
    public static function extractCoordinates(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $fromUrl = self::coordinatesFromText($url);
        if ($fromUrl !== null) {
            return [
                'latitude' => $fromUrl['latitude'],
                'longitude' => $fromUrl['longitude'],
                'url' => $url,
            ];
        }

        $resolved = self::followRedirects($url);
        if ($resolved === null) {
            return null;
        }

        $fromFinal = self::coordinatesFromText($resolved['url'])
            ?? self::coordinatesFromText($resolved['body']);

        if ($fromFinal === null) {
            return null;
        }

        return [
            'latitude' => $fromFinal['latitude'],
            'longitude' => $fromFinal['longitude'],
            'url' => $resolved['url'],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public static function coordinatesFromText(string $text): ?array
    {
        $patterns = [
            '/\/search\/(-?\d+\.?\d*),\s*\+?(-?\d+\.?\d*)/',
            '/@(-?\d+\.?\d*),(-?\d+\.?\d*)/',
            '/!3d(-?\d+\.?\d*)!4d(-?\d+\.?\d*)/',
            '/[?&]ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/',
            '/[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/',
            '/[?&]query=(-?\d+\.?\d*),\s*\+?(-?\d+\.?\d*)/',
            '/[?&]center=(-?\d+\.?\d*),(-?\d+\.?\d*)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $latitude = (float) $matches[1];
                $longitude = (float) $matches[2];
                if (self::isValidCoordinate($latitude, $longitude)) {
                    return [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ];
                }
            }
        }

        return null;
    }

    public static function isValidCoordinate(float $latitude, float $longitude): bool
    {
        return $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180
            && ! ($latitude == 0.0 && $longitude == 0.0);
    }

    /**
     * @return array{url: string, body: string}|null
     */
    public static function followRedirects(string $url, int $maxRedirects = 10): ?array
    {
        try {
            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                    'User-Agent' => 'Mozilla/5.0 (compatible; Facilya/1.0)',
                ])
                ->withOptions([
                    'allow_redirects' => ['max' => $maxRedirects],
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Google Maps Extractor — HTTP '.$response->status(), [
                    'url' => $url,
                ]);

                return null;
            }

            return [
                'url' => (string) $response->effectiveUri(),
                'body' => (string) $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Google Maps Extractor — '.$e->getMessage(), [
                'url' => $url,
            ]);

            return null;
        }
    }
}
