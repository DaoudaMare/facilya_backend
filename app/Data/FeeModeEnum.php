<?php

namespace App\Data;

use Filament\Support\Contracts\HasLabel;

enum FeeModeEnum: string implements HasLabel
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Montant fixe',
            self::PERCENTAGE => 'Pourcentage',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }
}
