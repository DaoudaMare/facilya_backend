<?php

namespace App\Data;

use Filament\Support\Contracts\HasLabel;

enum UserAddressKindEnum: string implements HasLabel
{
    case Home = 'home';
    case Shop = 'shop';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Domicile',
            self::Shop => 'Boutique',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }
}
