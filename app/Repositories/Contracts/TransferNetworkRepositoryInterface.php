<?php

namespace App\Repositories\Contracts;

use App\Data\TransferNetworkEnum;
use App\Models\TransferNetwork;
use Illuminate\Database\Eloquent\Collection;

interface TransferNetworkRepositoryInterface
{
    public function find(int $id): ?TransferNetwork;

    public function findOrFail(int $id): TransferNetwork;

    public function findByCode(TransferNetworkEnum|string $code): ?TransferNetwork;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TransferNetwork;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TransferNetwork $network, array $attributes): TransferNetwork;

    public function delete(TransferNetwork $network): bool;

    /**
     * @return Collection<int, TransferNetwork>
     */
    public function listActive(): Collection;
}
