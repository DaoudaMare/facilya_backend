<?php

namespace App\Services;

use App\Data\TransferNetworkEnum;
use App\Models\TransferNetwork;
use App\Repositories\Contracts\TransferNetworkRepositoryInterface;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Collection;

class TransferNetworkService
{
    public function __construct(
        protected TransferNetworkRepositoryInterface $networks,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TransferNetwork
    {
        $code = $attributes['code'] ?? null;
        $enum = $code instanceof TransferNetworkEnum
            ? $code
            : TransferNetworkEnum::tryFrom((string) $code);

        if ($enum && empty($attributes['name'])) {
            $attributes['name'] = $enum->label();
        }

        $attributes['can_send'] ??= true;
        $attributes['can_receive'] ??= true;
        $attributes['is_active'] ??= true;
        $attributes = $this->normalizePaymentFields($attributes);

        return $this->networks->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TransferNetwork $network, array $attributes): TransferNetwork
    {
        return $this->networks->update($network, $this->normalizePaymentFields($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizePaymentFields(array $attributes): array
    {
        if (array_key_exists('receive_phone', $attributes)) {
            $phone = trim((string) ($attributes['receive_phone'] ?? ''));
            $attributes['receive_phone'] = $phone === '' ? null : Phone::normalize($phone);
        }

        if (array_key_exists('payment_ussd', $attributes)) {
            $ussd = trim((string) ($attributes['payment_ussd'] ?? ''));
            $attributes['payment_ussd'] = $ussd === '' ? null : $ussd;
        }

        return $attributes;
    }

    public function delete(TransferNetwork $network): bool
    {
        return $this->networks->delete($network);
    }

    public function find(int $id): ?TransferNetwork
    {
        return $this->networks->find($id);
    }

    public function findByCode(TransferNetworkEnum|string $code): ?TransferNetwork
    {
        return $this->networks->findByCode($code);
    }

    /**
     * @return Collection<int, TransferNetwork>
     */
    public function listActive(): Collection
    {
        return $this->networks->listActive();
    }
}
