<?php

namespace App\Data;

use Filament\Support\Contracts\HasLabel;

enum TransferNetworkEnum: string implements HasLabel
{
    case MOOV = 'MOOV';
    case ORANGE = 'ORANGE';
    case WAVE = 'WAVE';
    case TELECEL = 'TELECEL';

    public function label(): string
    {
        return match ($this) {
            self::MOOV => 'Moov Money',
            self::ORANGE => 'Orange Money',
            self::WAVE => 'Wave',
            self::TELECEL => 'Telecel Money',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }
}