<?php

namespace App\Http\Resources;

use App\Models\TravelCompanyRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TravelCompanyRoute
 */
class TravelRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->travel_company_id,
            'company' => $this->travelCompany?->name,
            'departure' => $this->departure,
            'arrival' => $this->arrival,
            'travel_type' => $this->travel_type?->label() ?? $this->travel_type,
            'price' => (int) round((float) ($this->price ?? 0)),
            'accepts_parcels' => (bool) $this->accepts_parcels,
            'label' => sprintf(
                '%s — %s → %s',
                $this->travelCompany?->name ?? 'Compagnie',
                $this->departure,
                $this->arrival,
            ),
        ];
    }
}
