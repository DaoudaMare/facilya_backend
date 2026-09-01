<?php

namespace App\Repositories;

use App\Data\TransferNetworkEnum;
use App\Models\TransferNetwork;
use App\Repositories\Contracts\TransferNetworkRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TransferNetwork>
 */
class TransferNetworkRepository extends BaseRepository implements TransferNetworkRepositoryInterface
{
    protected function model(): string
    {
        return TransferNetwork::class;
    }

    public function find(int $id): ?TransferNetwork
    {
        return parent::find($id);
    }

    public function findOrFail(int $id): TransferNetwork
    {
        return parent::findOrFail($id);
    }

    public function findByCode(TransferNetworkEnum|string $code): ?TransferNetwork
    {
        $value = $code instanceof TransferNetworkEnum ? $code->value : $code;

        return $this->query()->where('code', $value)->first();
    }

    public function create(array $attributes): TransferNetwork
    {
        return parent::create($attributes);
    }

    public function update(TransferNetwork $network, array $attributes): TransferNetwork
    {
        return $this->persist($network, $attributes);
    }

    public function delete(TransferNetwork $network): bool
    {
        return $this->destroy($network);
    }

    /**
     * @return Collection<int, TransferNetwork>
     */
    public function listActive(): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
