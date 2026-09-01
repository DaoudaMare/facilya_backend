<?php

namespace App\Filament\Resources\TravelCompanyRoutes\Tables;

use App\Data\TravelTypeEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TravelCompanyRoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('travelCompany.name')
                    ->label('Compagnie')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('departure')
                    ->label('Départ')
                    ->searchable(),
                TextColumn::make('arrival')
                    ->label('Arrivée')
                    ->searchable(),
                TextColumn::make('travel_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('price')
                    ->label('Prix')
                    ->numeric()
                    ->suffix(' F CFA')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('travel_company_id')
                    ->label('Compagnie')
                    ->relationship('travelCompany', 'name'),
                SelectFilter::make('travel_type')
                    ->label('Type')
                    ->options(TravelTypeEnum::class),
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
