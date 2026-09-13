<?php

namespace App\Data;

readonly class TrustedPaymentQuote
{
    public function __construct(
        public string $merchandiseAmount,
        public string $networkFee,
        public string $platformFee,
        public string $payoutAmount,
    ) {}

    public function totalFees(): string
    {
        return bcadd($this->networkFee, $this->platformFee, 2);
    }

    public function totalAmount(): string
    {
        return bcadd($this->merchandiseAmount, $this->totalFees(), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'merchandise_amount' => (float) $this->merchandiseAmount,
            'network_fee' => (float) $this->networkFee,
            'platform_fee' => (float) $this->platformFee,
            'total_fee' => (float) $this->totalFees(),
            'total_amount' => (float) $this->totalAmount(),
            'payout_amount' => (float) $this->payoutAmount,
            'currency' => 'XOF',
        ];
    }
}
