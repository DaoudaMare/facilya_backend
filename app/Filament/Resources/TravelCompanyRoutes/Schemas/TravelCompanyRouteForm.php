<?php

namespace App\Filament\Resources\TravelCompanyRoutes\Schemas;

use App\Data\TravelTypeEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TravelCompanyRouteForm
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
                        Select::make('travel_type')
                            ->label('Type')
                            ->options(TravelTypeEnum::class)
                            ->required(),
                        TextInput::make('departure')
                            ->label('Départ')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('arrival')
                            ->label('Arrivée')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('price')
                            ->label('Prix')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('F CFA'),
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),
                    ]),
            ]);
    }
}
