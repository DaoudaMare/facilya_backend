<?php

namespace App\Services;

use App\Data\FeeModeEnum;
use App\Data\FeePartEnum;
use App\Data\FeeQuote;
use App\Data\TransactionTypeEnum;
use App\Models\Fee;
use App\Repositories\Contracts\FeeRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyRouteRepositoryInterface;

class FeeQuoteService
{
    public function __construct(
        protected FeeRepositoryInterface $fees,
        protected TravelCompanyRouteRepositoryInterface $routes,
    ) {}

    public function quote(
        TransactionTypeEnum $type,
        string $amount,
        ?int $networkId = null,
        ?int $counterpartNetworkId = null,
    ): FeeQuote {
        $normalized = number_format((float) $amount, 2, '.', '');

        if ((float) $normalized <= 0) {
            return FeeQuote::empty($normalized);
        }

        $networkRule = $this->resolve($type, FeePartEnum::NETWORK, $normalized, $networkId, $counterpartNetworkId);
        $platformRule = $this->resolve($type, FeePartEnum::PLATFORM, $normalized, $networkId, $counterpartNetworkId);

        $networkFee = $networkRule ? $this->compute($networkRule, $normalized) : '0.00';
        $platformFee = $platformRule ? $this->compute($platformRule, $normalized) : '0.00';

        return new FeeQuote($networkFee, $platformFee, $normalized);
    }

    public function resolve(
        TransactionTypeEnum $type,
        FeePartEnum $part,
        string $amount,
        ?int $networkId = null,
        ?int $counterpartNetworkId = null,
    ): ?Fee {
        return $this->fees
            ->findCandidates($type, $part, $amount)
            ->map(fn (Fee $fee) => [
                'fee' => $fee,
                'score' => $fee->specificityScore($networkId, $counterpartNetworkId),
            ])
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->pluck('fee')
            ->first();
    }

    public function compute(Fee $fee, string $amount): string
    {
        if ($fee->mode === FeeModeEnum::FIXED) {
            return number_format((float) $fee->value, 2, '.', '');
        }

        $computed = bcmul($amount, bcdiv((string) $fee->value, '100', 8), 2);

        if ($fee->min_fee !== null && bccomp($computed, (string) $fee->min_fee, 2) === -1) {
            $computed = number_format((float) $fee->min_fee, 2, '.', '');
        }

        if ($fee->max_fee !== null && bccomp($computed, (string) $fee->max_fee, 2) === 1) {
            $computed = number_format((float) $fee->max_fee, 2, '.', '');
        }

        return $computed;
    }

    public function serviceAmountForTicket(int $routeId, int $passengerCount = 1): string
    {
        $price = $this->routes->priceOf($routeId);

        if ($price === null) {
            return '0.00';
        }

        $seats = max(1, $passengerCount);

        return bcmul($price, (string) $seats, 2);
    }
}
