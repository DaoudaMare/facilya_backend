<?php

namespace App\Models;

use App\Data\FeeModeEnum;
use App\Data\FeePartEnum;
use App\Data\TransactionTypeEnum;
use App\Services\FeeQuoteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fee extends Model
{
    protected $fillable = [
        'name',
        'transaction_type',
        'part',
        'mode',
        'value',
        'min_fee',
        'max_fee',
        'min_amount',
        'max_amount',
        'network_id',
        'counterpart_network_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionTypeEnum::class,
            'part' => FeePartEnum::class,
            'mode' => FeeModeEnum::class,
            'value' => 'decimal:4',
            'min_fee' => 'decimal:2',
            'max_fee' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(TransferNetwork::class, 'network_id');
    }

    public function counterpartNetwork(): BelongsTo
    {
        return $this->belongsTo(TransferNetwork::class, 'counterpart_network_id');
    }

    public function compute(string $amount): string
    {
        return app(FeeQuoteService::class)->compute($this, $amount);
    }

    /**
     * @return array{network_fee: string, platform_fee: string, total_fee: string, amount: string, total_amount: string}
     */
    public static function quote(
        TransactionTypeEnum $type,
        string $amount,
        ?int $networkId = null,
        ?int $counterpartNetworkId = null,
    ): array {
        return app(FeeQuoteService::class)
            ->quote($type, $amount, $networkId, $counterpartNetworkId)
            ->toArray();
    }

    public static function resolve(
        TransactionTypeEnum $type,
        FeePartEnum $part,
        string $amount,
        ?int $networkId = null,
        ?int $counterpartNetworkId = null,
    ): ?self {
        return app(FeeQuoteService::class)->resolve($type, $part, $amount, $networkId, $counterpartNetworkId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForAmount(Builder $query, string $amount): Builder
    {
        return $query
            ->where(fn (Builder $builder) => $builder
                ->whereNull('min_amount')
                ->orWhere('min_amount', '<=', $amount))
            ->where(fn (Builder $builder) => $builder
                ->whereNull('max_amount')
                ->orWhere('max_amount', '>=', $amount));
    }

    public function specificityScore(?int $networkId, ?int $counterpartNetworkId): int
    {
        $ruleNetworkId = $this->network_id;
        $ruleCounterpartId = $this->counterpart_network_id;

        if ($ruleNetworkId === null && $ruleCounterpartId === null) {
            return 1;
        }

        if ($ruleNetworkId !== null && (int) $ruleNetworkId !== (int) $networkId) {
            return 0;
        }

        if ($ruleCounterpartId === null) {
            return 2;
        }

        return (int) $ruleCounterpartId === (int) $counterpartNetworkId ? 3 : 0;
    }
}
