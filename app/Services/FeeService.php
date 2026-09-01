<?php

namespace App\Services;

use App\Data\FeeModeEnum;
use App\Data\TransactionTypeEnum;
use App\Models\Fee;
use App\Repositories\Contracts\FeeRepositoryInterface;
use Illuminate\Validation\ValidationException;

class FeeService
{
    public function __construct(
        protected FeeRepositoryInterface $fees,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Fee
    {
        return $this->fees->create($this->sanitize($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Fee $fee, array $attributes): Fee
    {
        return $this->fees->update($fee, $this->sanitize($attributes, $fee));
    }

    public function delete(Fee $fee): bool
    {
        return $this->fees->delete($fee);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function sanitize(array $attributes, ?Fee $existing = null): array
    {
        $type = $attributes['transaction_type'] ?? $existing?->transaction_type;
        $type = $type instanceof TransactionTypeEnum
            ? $type
            : TransactionTypeEnum::tryFrom((string) $type);

        $mode = $attributes['mode'] ?? $existing?->mode;
        $mode = $mode instanceof FeeModeEnum
            ? $mode
            : FeeModeEnum::tryFrom((string) $mode);

        if ($type !== TransactionTypeEnum::NETWORK_TRANSFER) {
            $attributes['counterpart_network_id'] = null;
        }

        if ($mode !== FeeModeEnum::PERCENTAGE) {
            $attributes['min_fee'] = null;
            $attributes['max_fee'] = null;
        }

        if (
            isset($attributes['min_fee'], $attributes['max_fee'])
            && $attributes['min_fee'] !== null
            && $attributes['max_fee'] !== null
            && bccomp((string) $attributes['max_fee'], (string) $attributes['min_fee'], 2) === -1
        ) {
            throw ValidationException::withMessages([
                'max_fee' => 'Le plafond de frais doit être supérieur ou égal au minimum.',
            ]);
        }

        return $attributes;
    }
}
