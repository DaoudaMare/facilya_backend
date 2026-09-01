<?php

namespace App\Repositories;

use App\Models\TravelCompanyStation;
use App\Repositories\Contracts\TravelCompanyStationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TravelCompanyStation>
 */
class TravelCompanyStationRepository extends BaseRepository implements TravelCompanyStationRepositoryInterface
{
    protected function model(): string
    {
        return TravelCompanyStation::class;
    }

    public function find(int $id): ?TravelCompanyStation
    {
        return parent::find($id);
    }

    public function findOrFail(int $id): TravelCompanyStation
    {
        return parent::findOrFail($id);
    }

    public function create(array $attributes): TravelCompanyStation
    {
        return parent::create($attributes);
    }

    public function update(TravelCompanyStation $station, array $attributes): TravelCompanyStation
    {
        return $this->persist($station, $attributes);
    }

    public function delete(TravelCompanyStation $station): bool
    {
        return $this->destroy($station);
    }

    /**
     * @return Collection<int, TravelCompanyStation>
     */
    public function listActiveForCompany(int $companyId): Collection
    {
        return $this->query()
            ->where('travel_company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('station_name')
            ->get();
    }
}
