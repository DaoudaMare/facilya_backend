<?php

namespace App\Filament\Support;

use App\Filament\Livewire\RevealEscrowUnlockCode;
use App\Support\EscrowCodes;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;

class EscrowCodesSection
{
    public static function make(): Section
    {
        return Section::make('Identifiants escrow')
            ->description('Identifiant 6 chiffres (commerçant) et code de déblocage des fonds (protégé).')
            ->visible(fn (mixed $record): bool => EscrowCodes::from($record) !== null)
            ->schema([
                Livewire::make(
                    RevealEscrowUnlockCode::class,
                    fn (mixed $record): array => [
                        'trustedPaymentId' => (int) EscrowCodes::from($record)?->id,
                    ],
                ),
            ]);
    }
}
