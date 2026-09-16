<?php

namespace App\Services;

use App\Data\MapBoxRoutingProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapDirectionService
{
    public function __construct(
        protected string $urlBase = 'https://api.mapbox.com',
    ) {}

    /**
     * @param  array{latitude: float, longitude: float}  $origin
     * @param  array{latitude: float, longitude: float}  $destination
     * @return array<string, mixed>
     */
    public function getDirections(
        array $origin,
        array $destination,
        MapBoxRoutingProfile $mode = MapBoxRoutingProfile::Driving,
    ): array {
        $token = (string) config('services.mapbox.token');
        if ($token === '') {
            throw new \RuntimeException('Jeton Mapbox manquant (MAPBOX_ACCESS_TOKEN).');
        }

        $coordinates = $origin['longitude'].','.$origin['latitude'].';'
            .$destination['longitude'].','.$destination['latitude'];

        $response = Http::timeout(20)->get(
            $this->urlBase.'/directions/v5/mapbox/'.$mode->value.'/'.$coordinates,
            [
                'access_token' => $token,
                'geometries' => 'geojson',
                'overview' => 'simplified',
            ],
        );

        if ($response->failed()) {
            throw new \RuntimeException('Impossible de récupérer l’itinéraire Mapbox.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    /**
     * @param  array{latitude: float, longitude: float}  $pickup
     * @param  array{latitude: float, longitude: float}  $dropoff
     * @return array<string, mixed>
     */
    public function getDeliveryDirection(
        array $pickup,
        array $dropoff,
        MapBoxRoutingProfile $routingProfile = MapBoxRoutingProfile::Driving,
    ): array {
        Log::info('Appel Mapbox Directions API', [
            'origin' => $pickup,
            'destination' => $dropoff,
        ]);

        return $this->getDirections($pickup, $dropoff, $routingProfile);
    }

    /**
     * Distance routière en km (Mapbox), avec repli Haversine.
     *
     * @param  array{latitude: float, longitude: float}  $origin
     * @param  array{latitude: float, longitude: float}  $destination
     */
    public function drivingDistanceKm(array $origin, array $destination): float
    {
        try {
            $directionData = $this->getDeliveryDirection($origin, $destination);

            if (($directionData['code'] ?? null) !== 'Ok') {
                throw new \RuntimeException((string) ($directionData['message'] ?? $directionData['code'] ?? 'Mapbox error'));
            }

            $meters = $directionData['routes'][0]['distance'] ?? null;
            if (! is_numeric($meters)) {
                throw new \RuntimeException('Aucune distance dans la réponse Mapbox.');
            }

            return round(((float) $meters) / 1000, 2);
        } catch (\Throwable $e) {
            $fallback = $this->haversineKm($origin, $destination);
            Log::warning('Mapbox indisponible, distance Haversine', [
                'message' => $e->getMessage(),
                'distance_km' => $fallback,
            ]);

            return $fallback;
        }
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function geocode(string $query): ?array
    {
        $token = (string) config('services.mapbox.token');
        if ($token === '' || trim($query) === '') {
            return null;
        }

        try {
            $response = Http::timeout(15)->get(
                $this->urlBase.'/geocoding/v5/mapbox.places/'.rawurlencode($query).'.json',
                [
                    'access_token' => $token,
                    'limit' => 1,
                    'country' => 'BF',
                ],
            );

            if ($response->failed()) {
                return null;
            }

            $center = $response->json('features.0.center');
            if (! is_array($center) || count($center) < 2) {
                return null;
            }

            $longitude = (float) $center[0];
            $latitude = (float) $center[1];
            if (! GoogleMapsExtractor::isValidCoordinate($latitude, $longitude)) {
                return null;
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        } catch (\Throwable $e) {
            Log::warning('Géocodage Mapbox impossible', [
                'query' => $query,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array{latitude: float, longitude: float}  $origin
     * @param  array{latitude: float, longitude: float}  $destination
     */
    public function haversineKm(array $origin, array $destination): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($destination['latitude'] - $origin['latitude']);
        $dLng = deg2rad($destination['longitude'] - $origin['longitude']);
        $lat1 = deg2rad($origin['latitude']);
        $lat2 = deg2rad($destination['latitude']);

        $a = sin($dLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $c = 2 * asin(min(1, sqrt($a)));

        return round($earthKm * $c, 2);
    }
}
