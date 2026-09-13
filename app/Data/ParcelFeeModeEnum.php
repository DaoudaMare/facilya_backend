<?php

namespace App\Data;

use Filament\Support\Contracts\HasLabel;

enum ParcelFeeModeEnum: string implements HasLabel
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Montant fixe (F CFA)',
            self::Percentage => 'Pourcentage (%)',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }
}
