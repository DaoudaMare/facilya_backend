<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionTypeEnum: string implements HasColor, HasLabel
{
    case TICKET_PURCHASE = 'ticket_purchase';
    case NETWORK_TRANSFER = 'network_transfer';

    public function label(): string
    {
        return match ($this) {
            self::TICKET_PURCHASE => 'Achat de ticket',
            self::NETWORK_TRANSFER => 'Transfert inter-réseau',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::TICKET_PURCHASE => 'TK',
            self::NETWORK_TRANSFER => 'TF',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TICKET_PURCHASE => 'info',
            self::NETWORK_TRANSFER => 'warning',
        };
    }
}
