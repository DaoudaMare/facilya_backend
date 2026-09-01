<?php

namespace App\Repositories\Contracts;

use App\Models\TravelCompanyRoute;
use Illuminate\Database\Eloquent\Collection;

interface TravelCompanyRouteRepositoryInterface
{
    public function find(int $id): ?TravelCompanyRoute;

    public function findOrFail(int $id): TravelCompanyRoute;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TravelCompanyRoute;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TravelCompanyRoute $route, array $attributes): TravelCompanyRoute;

    public function delete(TravelCompanyRoute $route): bool;

    public function priceOf(int $id): ?string;

    /**
     * @return Collection<int, TravelCompanyRoute>
     */
    public function listActive(?int $companyId = null): Collection;

    /**
     * @return list<string>
     */
    public function distinctCities(): array;

    /**
     * @return list<array{departure: string, arrival: string, from_price: string}>
     */
    public function popularCorridors(int $limit = 6): array;
}
