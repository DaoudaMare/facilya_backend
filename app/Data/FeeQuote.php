<?php

namespace App\Data;

readonly class FeeQuote
{
    public function __construct(
        public string $networkFee = '0.00',
        public string $platformFee = '0.00',
        public string $serviceAmount = '0.00',
    ) {}

    public static function empty(string $serviceAmount = '0.00'): self
    {
        return new self(serviceAmount: number_format((float) $serviceAmount, 2, '.', ''));
    }

    public function totalFee(): string
    {
        return bcadd($this->networkFee, $this->platformFee, 2);
    }

    public function totalAmount(): string
    {
        return bcadd($this->serviceAmount, $this->totalFee(), 2);
    }

    public function withServiceAmount(string $amount): self
    {
        return new self(
            $this->networkFee,
            $this->platformFee,
            number_format((float) $amount, 2, '.', ''),
        );
    }

    /**
     * @return array{network_fee: string, platform_fee: string, total_fee: string, amount: string, total_amount: string}
     */
    public function toArray(): array
    {
        return [
            'network_fee' => $this->networkFee,
            'platform_fee' => $this->platformFee,
            'total_fee' => $this->totalFee(),
            'amount' => $this->serviceAmount,
            'total_amount' => $this->totalAmount(),
        ];
    }
}
