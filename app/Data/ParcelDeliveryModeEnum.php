<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ParcelDeliveryModeEnum: string implements HasColor, HasLabel
{
    case DoorDelivery = 'door_delivery';
    case StationPickup = 'station_pickup';

    public function label(): string
    {
        return match ($this) {
            self::DoorDelivery => 'Livraison à domicile',
            self::StationPickup => 'Retrait en gare',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DoorDelivery => 'success',
            self::StationPickup => 'info',
        };
    }
}
