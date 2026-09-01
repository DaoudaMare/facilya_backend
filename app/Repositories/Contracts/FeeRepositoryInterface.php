<?php

namespace App\Repositories\Contracts;

use App\Data\FeePartEnum;
use App\Data\TransactionTypeEnum;
use App\Models\Fee;
use Illuminate\Database\Eloquent\Collection;

interface FeeRepositoryInterface
{
    public function find(int $id): ?Fee;

    public function findOrFail(int $id): Fee;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Fee;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Fee $fee, array $attributes): Fee;

    public function delete(Fee $fee): bool;

    /**
     * @return Collection<int, Fee>
     */
    public function findCandidates(
        TransactionTypeEnum $type,
        FeePartEnum $part,
        string $amount,
    ): Collection;
}
