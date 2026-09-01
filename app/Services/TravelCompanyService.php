<?php

namespace App\Services;

use App\Models\TravelCompany;
use App\Models\TravelCompanyRoute;
use App\Models\TravelCompanyStation;
use App\Models\TravelCompanyTrip;
use App\Repositories\Contracts\TravelCompanyRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyRouteRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyStationRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyTripRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TravelCompanyService
{
    public function __construct(
        protected TravelCompanyRepositoryInterface $companies,
        protected TravelCompanyStationRepositoryInterface $stations,
        protected TravelCompanyRouteRepositoryInterface $routes,
        protected TravelCompanyTripRepositoryInterface $trips,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TravelCompany
    {
        $attributes['is_active'] ??= true;

        return $this->companies->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TravelCompany $company, array $attributes): TravelCompany
    {
        return $this->companies->update($company, $attributes);
    }

    public function delete(TravelCompany $company): bool
    {
        return $this->companies->delete($company);
    }

    public function find(int $id): ?TravelCompany
    {
        return $this->companies->find($id);
    }

    /**
     * @return Collection<int, TravelCompany>
     */
    public function listActive(): Collection
    {
        return $this->companies->listActive();
    }

    public function countActive(): int
    {
        return $this->companies->countActive();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createStation(array $attributes): TravelCompanyStation
    {
        $this->companies->findOrFail((int) $attributes['travel_company_id']);
        $attributes['is_active'] ??= true;

        return $this->stations->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStation(TravelCompanyStation $station, array $attributes): TravelCompanyStation
    {
        if (isset($attributes['travel_company_id'])) {
            $this->companies->findOrFail((int) $attributes['travel_company_id']);
        }

        return $this->stations->update($station, $attributes);
    }

    public function deleteStation(TravelCompanyStation $station): bool
    {
        return $this->stations->delete($station);
    }

    /**
     * @return Collection<int, TravelCompanyStation>
     */
    public function listActiveStations(int $companyId): Collection
    {
        return $this->stations->listActiveForCompany($companyId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRoute(array $attributes): TravelCompanyRoute
    {
        $this->companies->findOrFail((int) $attributes['travel_company_id']);
        $attributes['is_active'] ??= true;

        return $this->routes->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRoute(TravelCompanyRoute $route, array $attributes): TravelCompanyRoute
    {
        if (isset($attributes['travel_company_id'])) {
            $this->companies->findOrFail((int) $attributes['travel_company_id']);
        }

        return $this->routes->update($route, $attributes);
    }

    public function deleteRoute(TravelCompanyRoute $route): bool
    {
        return $this->routes->delete($route);
    }

    /**
     * @return Collection<int, TravelCompanyRoute>
     */
    public function listActiveRoutes(?int $companyId = null): Collection
    {
        return $this->routes->listActive($companyId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTrip(array $attributes): TravelCompanyTrip
    {
        $this->assertTripBelongsToSameCompany($attributes);
        $attributes['is_active'] ??= true;

        return $this->trips->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTrip(TravelCompanyTrip $trip, array $attributes): TravelCompanyTrip
    {
        $this->assertTripBelongsToSameCompany($attributes, $trip);

        return $this->trips->update($trip, $attributes);
    }

    public function deleteTrip(TravelCompanyTrip $trip): bool
    {
        return $this->trips->delete($trip);
    }

    /**
     * @return Collection<int, TravelCompanyTrip>
     */
    public function listActiveTripsForRoute(int $routeId): Collection
    {
        return $this->trips->listActiveForRoute($routeId);
    }

    /**
     * @return Collection<int, TravelCompanyTrip>
     */
    public function searchTrips(string $departure, string $arrival): Collection
    {
        return $this->trips->searchActive($departure, $arrival);
    }

    /**
     * @return list<string>
     */
    public function cities(): array
    {
        return $this->routes->distinctCities();
    }

    /**
     * @return list<array{departure: string, arrival: string, from_price: string}>
     */
    public function popularCorridors(int $limit = 6): array
    {
        return $this->routes->popularCorridors($limit);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function assertTripBelongsToSameCompany(array $attributes, ?TravelCompanyTrip $existing = null): void
    {
        $routeId = $attributes['travel_company_route_id'] ?? $existing?->travel_company_route_id;
        $stationId = $attributes['travel_company_station_id'] ?? $existing?->travel_company_station_id;

        if (! $routeId || ! $stationId) {
            return;
        }

        $route = $this->routes->findOrFail((int) $routeId);
        $station = $this->stations->findOrFail((int) $stationId);

        if ((int) $route->travel_company_id !== (int) $station->travel_company_id) {
            throw ValidationException::withMessages([
                'travel_company_station_id' => 'La gare doit appartenir à la même compagnie que le trajet.',
            ]);
        }
    }
}
