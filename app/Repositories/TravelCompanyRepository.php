<?php

namespace App\Repositories;

use App\Models\TravelCompany;
use App\Repositories\Contracts\TravelCompanyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TravelCompany>
 */
class TravelCompanyRepository extends BaseRepository implements TravelCompanyRepositoryInterface
{
    protected function model(): string
    {
        return TravelCompany::class;
    }

    public function find(int $id): ?TravelCompany
    {
        return parent::find($id);
    }

    public function findOrFail(int $id): TravelCompany
    {
        return parent::findOrFail($id);
    }

    public function create(array $attributes): TravelCompany
    {
        return parent::create($attributes);
    }

    public function update(TravelCompany $company, array $attributes): TravelCompany
    {
        return $this->persist($company, $attributes);
    }

    public function delete(TravelCompany $company): bool
    {
        return $this->destroy($company);
    }

    /**
     * @return Collection<int, TravelCompany>
     */
    public function listActive(): Collection
    {
        return $this->query()->where('is_active', true)->orderBy('name')->get();
    }

    public function countActive(): int
    {
        return $this->query()->where('is_active', true)->count();
    }
}
