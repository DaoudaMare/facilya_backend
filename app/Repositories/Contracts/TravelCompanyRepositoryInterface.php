<?php

namespace App\Repositories\Contracts;

use App\Models\TravelCompany;
use Illuminate\Database\Eloquent\Collection;

interface TravelCompanyRepositoryInterface
{
    public function find(int $id): ?TravelCompany;

    public function findOrFail(int $id): TravelCompany;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TravelCompany;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TravelCompany $company, array $attributes): TravelCompany;

    public function delete(TravelCompany $company): bool;

    /**
     * @return Collection<int, TravelCompany>
     */
    public function listActive(): Collection;

    public function countActive(): int;
}
