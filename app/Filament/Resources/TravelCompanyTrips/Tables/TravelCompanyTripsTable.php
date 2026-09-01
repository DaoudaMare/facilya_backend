<?php

namespace App\Filament\Resources\TravelCompanyTrips\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TravelCompanyTripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('route.travelCompany.name')
                    ->label('Compagnie')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('route.departure')
                    ->label('Départ')
                    ->searchable(),
                TextColumn::make('route.arrival')
                    ->label('Arrivée')
                    ->searchable(),
                TextColumn::make('station.station_name')
                    ->label('Gare')
                    ->searchable(),
                TextColumn::make('departure_hour')
                    ->label('Départ')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('arrival_hour')
                    ->label('Arrivée')
                    ->time('H:i'),
                TextColumn::make('available_seats')
                    ->label('Places')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('travel_company_route_id')
                    ->label('Trajet')
                    ->relationship('route', 'departure'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
