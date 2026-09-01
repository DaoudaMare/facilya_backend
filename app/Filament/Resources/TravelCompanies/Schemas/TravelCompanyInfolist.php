<?php

namespace App\Filament\Resources\TravelCompanies\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TravelCompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nom'),
                        TextEntry::make('email')->label('Email')->placeholder('—'),
                        TextEntry::make('phone')->label('Téléphone')->placeholder('—'),
                        TextEntry::make('address')->label('Adresse')->placeholder('—'),
                        TextEntry::make('google_maps_link')
                            ->label('Google Maps')
                            ->url(fn (?string $state): ?string => $state)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        IconEntry::make('is_active')->label('Active')->boolean(),
                    ]),
            ]);
    }
}
