<?php

namespace App\Repositories;

use App\Models\TravelCompanyTrip;
use App\Repositories\Contracts\TravelCompanyTripRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TravelCompanyTrip>
 */
class TravelCompanyTripRepository extends BaseRepository implements TravelCompanyTripRepositoryInterface
{
    protected function model(): string
    {
        return TravelCompanyTrip::class;
    }

    public function find(int $id): ?TravelCompanyTrip
    {
        return parent::find($id);
    }

    public function findOrFail(int $id): TravelCompanyTrip
    {
        return parent::findOrFail($id);
    }

    public function create(array $attributes): TravelCompanyTrip
    {
        return parent::create($attributes);
    }

    public function update(TravelCompanyTrip $trip, array $attributes): TravelCompanyTrip
    {
        return $this->persist($trip, $attributes);
    }

    public function delete(TravelCompanyTrip $trip): bool
    {
        return $this->destroy($trip);
    }

    public function routeIdOf(int $tripId): ?int
    {
        $routeId = $this->query()->whereKey($tripId)->value('travel_company_route_id');

        return $routeId === null ? null : (int) $routeId;
    }

    /**
     * @return Collection<int, TravelCompanyTrip>
     */
    public function listActiveForRoute(int $routeId): Collection
    {
        return $this->query()
            ->with(['station', 'route.travelCompany'])
            ->where('travel_company_route_id', $routeId)
            ->where('is_active', true)
            ->orderBy('departure_hour')
            ->get();
    }

    /**
     * @return Collection<int, TravelCompanyTrip>
     */
    public function searchActive(string $departure, string $arrival): Collection
    {
        return $this->query()
            ->with(['station', 'route.travelCompany'])
            ->where('is_active', true)
            ->whereHas('route', function ($query) use ($departure, $arrival) {
                $query
                    ->where('is_active', true)
                    ->whereRaw('LOWER(departure) = ?', [mb_strtolower(trim($departure))])
                    ->whereRaw('LOWER(arrival) = ?', [mb_strtolower(trim($arrival))]);
            })
            ->orderBy('departure_hour')
            ->get();
    }
}
