<?php

namespace App\Filament\Resources\TravelCompanyStations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TravelCompanyStationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('travel_company_id')
                            ->label('Compagnie')
                            ->relationship('travelCompany', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('station_name')
                            ->label('Nom de la gare')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email(),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel(),
                        TextInput::make('address')
                            ->label('Adresse')
                            ->columnSpanFull(),
                        TextInput::make('google_maps_link')
                            ->label('Lien Google Maps')
                            ->url()
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }
}
