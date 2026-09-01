<?php

namespace App\Http\Resources;

use App\Models\TravelCompanyTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TravelCompanyTrip
 */
class TravelTripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $company = $this->route?->travelCompany;
        $route = $this->route;
        $station = $this->station;
        $name = $company?->name ?? 'Compagnie';

        return [
            'id' => $this->id,
            'route_id' => $this->travel_company_route_id,
            'company' => $name,
            'color' => $this->brandColor($name),
            'departure_city' => $route?->departure,
            'arrival_city' => $route?->arrival,
            'departure_hour' => $this->formattedDeparture(),
            'arrival_hour' => $this->formattedArrival(),
            'duration' => $this->durationLabel(),
            'type' => $route?->travel_type?->label() ?? $route?->travel_type,
            'type_code' => $route?->travel_type?->value,
            'price' => (int) round((float) ($route?->price ?? 0)),
            'seats' => $this->available_seats,
            'boarding_point' => $station?->station_name ?? $station?->address,
        ];
    }

    protected function brandColor(string $name): string
    {
        $palette = ['#16A34A', '#0B1F3A', '#FF6B00', '#8B5CF6', '#005BAB', '#E30613'];

        return $palette[abs(crc32($name)) % count($palette)];
    }
}
