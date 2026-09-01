<?php

namespace App\Repositories\Contracts;

use App\Models\TravelCompanyStation;
use Illuminate\Database\Eloquent\Collection;

interface TravelCompanyStationRepositoryInterface
{
    public function find(int $id): ?TravelCompanyStation;

    public function findOrFail(int $id): TravelCompanyStation;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TravelCompanyStation;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TravelCompanyStation $station, array $attributes): TravelCompanyStation;

    public function delete(TravelCompanyStation $station): bool;

    /**
     * @return Collection<int, TravelCompanyStation>
     */
    public function listActiveForCompany(int $companyId): Collection;
}
