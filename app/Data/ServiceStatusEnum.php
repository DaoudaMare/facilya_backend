<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceStatusEnum: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Service en attente',
            self::PROCESSING => 'Service en cours',
            self::DELIVERED => 'Service livré',
            self::FAILED => 'Service échoué',
            self::CANCELLED => 'Service annulé',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::FAILED, self::CANCELLED], true);
    }

    public function isDelivered(): bool
    {
        return $this === self::DELIVERED;
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
            self::DELIVERED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'warning',
        };
    }
}
