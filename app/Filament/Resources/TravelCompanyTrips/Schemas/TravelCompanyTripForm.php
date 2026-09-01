<?php

namespace App\Filament\Resources\TravelCompanyTrips\Schemas;

use App\Models\TravelCompanyRoute;
use App\Models\TravelCompanyStation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TravelCompanyTripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('travel_company_route_id')
                            ->label('Trajet')
                            ->relationship(
                                'route',
                                'departure',
                                fn (Builder $query) => $query->with('travelCompany'),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (TravelCompanyRoute $record): string => sprintf(
                                    '%s — %s → %s (%s)',
                                    $record->travelCompany?->name,
                                    $record->departure,
                                    $record->arrival,
                                    $record->travel_type?->label(),
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('travel_company_station_id')
                            ->label('Gare de départ')
                            ->relationship(
                                'station',
                                'station_name',
                                fn (Builder $query) => $query->with('travelCompany'),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (TravelCompanyStation $record): string => sprintf(
                                    '%s — %s',
                                    $record->travelCompany?->name,
                                    $record->station_name,
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        TimePicker::make('departure_hour')
                            ->label('Heure de départ')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('arrival_hour')
                            ->label('Heure d’arrivée')
                            ->seconds(false),
                        TextInput::make('available_seats')
                            ->label('Places disponibles')
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),
                    ]),
            ]);
    }
}
