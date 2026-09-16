<?php

namespace Tests\Unit;

use App\Support\GoogleMapsLink;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleMapsLinkTest extends TestCase
{
    #[DataProvider('validLinks')]
    public function test_accepts_google_maps_links(string $url): void
    {
        $this->assertTrue(GoogleMapsLink::isValid($url));
    }

    #[DataProvider('invalidLinks')]
    public function test_rejects_non_google_maps_links(string $url): void
    {
        $this->assertFalse(GoogleMapsLink::isValid($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validLinks(): array
    {
        return [
            'short share' => ['https://maps.app.goo.gl/AbCdEf123'],
            'maps google' => ['https://maps.google.com/?q=12.37,-1.52'],
            'google maps place' => ['https://www.google.com/maps/place/Ouagadougou'],
            'google maps search' => ['https://google.com/maps/search/?api=1&query=Ouaga'],
            'http goo.gl maps' => ['http://goo.gl/maps/xyz123'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidLinks(): array
    {
        return [
            'plain text' => ['Ouaga, secteur 15'],
            'other site' => ['https://openstreetmap.org/#map=15/12.3/-1.5'],
            'google without maps' => ['https://www.google.com/search?q=ouaga'],
            'empty' => [''],
            'not url' => ['maps.app.goo.gl/abc'],
        ];
    }
}
