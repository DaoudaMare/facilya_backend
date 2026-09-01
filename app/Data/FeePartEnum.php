<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FeePartEnum: string implements HasColor, HasLabel
{
    case NETWORK = 'network';
    case PLATFORM = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::NETWORK => 'Frais réseau',
            self::PLATFORM => 'Frais plateforme',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NETWORK => 'warning',
            self::PLATFORM => 'primary',
        };
    }
}
