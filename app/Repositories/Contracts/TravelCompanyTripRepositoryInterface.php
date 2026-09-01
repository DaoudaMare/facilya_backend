<?php

namespace App\Repositories\Contracts;

use App\Models\TravelCompanyTrip;
use Illuminate\Database\Eloquent\Collection;

interface TravelCompanyTripRepositoryInterface
{
    public function find(int $id): ?TravelCompanyTrip;

    public function findOrFail(int $id): TravelCompanyTrip;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TravelCompanyTrip;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TravelCompanyTrip $trip, array $attributes): TravelCompanyTrip;

    public function delete(TravelCompanyTrip $trip): bool;

    public function routeIdOf(int $tripId): ?int;

    /**
     * @return Collection<int, TravelCompanyTrip>
     */
    public function listActiveForRoute(int $routeId): Collection;

    /**
     * @return Collection<int, TravelCompanyTrip>
     */
    public function searchActive(string $departure, string $arrival): Collection;
}
