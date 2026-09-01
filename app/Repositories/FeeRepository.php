<?php

namespace App\Repositories;

use App\Data\FeePartEnum;
use App\Data\TransactionTypeEnum;
use App\Models\Fee;
use App\Repositories\Contracts\FeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Fee>
 */
class FeeRepository extends BaseRepository implements FeeRepositoryInterface
{
    protected function model(): string
    {
        return Fee::class;
    }

    public function find(int $id): ?Fee
    {
        return parent::find($id);
    }

    public function findOrFail(int $id): Fee
    {
        return parent::findOrFail($id);
    }

    public function create(array $attributes): Fee
    {
        return parent::create($attributes);
    }

    public function update(Fee $fee, array $attributes): Fee
    {
        return $this->persist($fee, $attributes);
    }

    public function delete(Fee $fee): bool
    {
        return $this->destroy($fee);
    }

    /**
     * @return Collection<int, Fee>
     */
    public function findCandidates(
        TransactionTypeEnum $type,
        FeePartEnum $part,
        string $amount,
    ): Collection {
        return $this->query()
            ->active()
            ->where('transaction_type', $type)
            ->where('part', $part)
            ->forAmount($amount)
            ->get();
    }
}
