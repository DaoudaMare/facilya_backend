<?php

namespace App\Data;

readonly class ParcelQuote
{
    public function __construct(
        public string $agencyFee = '0.00',
        public string $marginAmount = '0.00',
        public string $baseAmount = '0.00',
        public string $valueFee = '0.00',
        public string $pickupBaseFee = '0.00',
        public string $pickupDistanceFee = '0.00',
        public string $pickupFee = '0.00',
        public string $deliveryBaseFee = '0.00',
        public string $deliveryDistanceFee = '0.00',
        public string $deliveryFee = '0.00',
        public string $networkFee = '0.00',
        public string $platformFee = '0.00',
        public ?float $pickupDistanceKm = null,
        public ?float $deliveryDistanceKm = null,
        public ?float $declaredValue = null,
        public string $valueFeePercent = '0.00',
        public string $pickupPerKm = '0.00',
        public string $deliveryPerKm = '0.00',
    ) {}

    public function serviceAmount(): string
    {
        return bcadd(
            bcadd(bcadd($this->baseAmount, $this->valueFee, 2), $this->pickupFee, 2),
            $this->deliveryFee,
            2,
        );
    }

    public function totalFee(): string
    {
        return bcadd($this->networkFee, $this->platformFee, 2);
    }

    public function totalAmount(): string
    {
        return bcadd($this->serviceAmount(), $this->totalFee(), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'agency_fee' => (float) $this->agencyFee,
            'margin_amount' => (float) $this->marginAmount,
            'base_amount' => (float) $this->baseAmount,
            'declared_value' => $this->declaredValue,
            'value_fee_percent' => (float) $this->valueFeePercent,
            'value_fee' => (float) $this->valueFee,
            'pickup' => [
                'base_fee' => (float) $this->pickupBaseFee,
                'distance_km' => $this->pickupDistanceKm,
                'per_km' => (float) $this->pickupPerKm,
                'distance_fee' => (float) $this->pickupDistanceFee,
                'total' => (float) $this->pickupFee,
            ],
            'delivery' => [
                'base_fee' => (float) $this->deliveryBaseFee,
                'distance_km' => $this->deliveryDistanceKm,
                'per_km' => (float) $this->deliveryPerKm,
                'distance_fee' => (float) $this->deliveryDistanceFee,
                'total' => (float) $this->deliveryFee,
            ],
            'pickup_fee' => (float) $this->pickupFee,
            'delivery_fee' => (float) $this->deliveryFee,
            'network_fee' => (float) $this->networkFee,
            'platform_fee' => (float) $this->platformFee,
            'amount' => (float) $this->serviceAmount(),
            'total_fee' => (float) $this->totalFee(),
            'total_amount' => (float) $this->totalAmount(),
        ];
    }
}
