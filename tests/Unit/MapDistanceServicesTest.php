<?php

namespace Tests\Unit;

use App\Services\GoogleMapsExtractor;
use App\Services\MapDirectionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapDistanceServicesTest extends TestCase
{
    public function test_extracts_coordinates_from_maps_url_without_http(): void
    {
        $coords = GoogleMapsExtractor::extractCoordinates(
            'https://www.google.com/maps/place/Ouaga/@12.3714,-1.5197,17z',
        );

        $this->assertNotNull($coords);
        $this->assertEquals(12.3714, $coords['latitude']);
        $this->assertEquals(-1.5197, $coords['longitude']);
    }

    public function test_follows_short_link_to_extract_coordinates(): void
    {
        Http::fake([
            'https://maps.app.goo.gl/abc' => Http::response(
                '<html>@12.35,-1.52,15z</html>',
                200,
            ),
        ]);

        $coords = GoogleMapsExtractor::extractCoordinates('https://maps.app.goo.gl/abc');

        $this->assertNotNull($coords);
        $this->assertEquals(12.35, $coords['latitude']);
        $this->assertEquals(-1.52, $coords['longitude']);
    }

    public function test_mapbox_distance_is_converted_to_km(): void
    {
        Http::fake([
            'https://api.mapbox.com/directions/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 4500, 'duration' => 700]],
            ], 200),
        ]);

        $km = app(MapDirectionService::class)->drivingDistanceKm(
            ['latitude' => 12.37, 'longitude' => -1.52],
            ['latitude' => 12.35, 'longitude' => -1.53],
        );

        $this->assertEquals(4.5, $km);
    }
}
