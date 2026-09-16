<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ParcelScopeEnum: string implements HasColor, HasLabel
{
    case Local = 'local';
    case Intercity = 'intercity';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Livraison locale',
            self::Intercity => 'Livraison inter-villes',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Local => 'info',
            self::Intercity => 'primary',
        };
    }
}
