<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatusEnum: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case RECEIVED = 'received';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Paiement en attente',
            self::PROCESSING => 'Paiement en cours',
            self::RECEIVED => 'Paiement reçu',
            self::FAILED => 'Paiement échoué',
            self::CANCELLED => 'Paiement annulé',
            self::REFUNDED => 'Paiement remboursé',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::RECEIVED, self::FAILED, self::CANCELLED, self::REFUNDED], true);
    }

    public function isReceived(): bool
    {
        return $this === self::RECEIVED;
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PROCESSING => 'info',
            self::RECEIVED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'warning',
            self::REFUNDED => 'primary',
        };
    }
}
