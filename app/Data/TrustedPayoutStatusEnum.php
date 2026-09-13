<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TrustedPayoutStatusEnum: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Versement en attente',
            self::Processing => 'Versement en cours',
            self::Sent => 'Versé au commerçant',
            self::Failed => 'Versement échoué',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
