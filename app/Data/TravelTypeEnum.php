<?php

namespace App\Data;

use Filament\Support\Contracts\HasLabel;

enum TravelTypeEnum: string implements HasLabel
{
    case CLASSIC = 'classic';
    case VIP = 'vip';
    case AC = 'ac';

    public function label(): string
    {
        return match ($this) {
            self::CLASSIC => 'Classique',
            self::VIP => 'VIP',
            self::AC => 'Climatisé',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }
}
