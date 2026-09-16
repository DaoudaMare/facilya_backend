<?php

namespace App\Filament\Resources\ParcelShipments\Tables;

use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelStatusEnum;
use App\Filament\Resources\ParcelShipments\Support\ParcelShipmentStatusActions;
use App\Models\ParcelShipment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ParcelShipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable()
                    ->description(fn (ParcelShipment $record): ?string => ParcelShipmentStatusActions::nextStepHint($record)),
                TextColumn::make('scope')
                    ->label('Type')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('delivery_mode')
                    ->label('Livraison')
                    ->badge(),
                TextColumn::make('route.departure')
                    ->label('Départ')
                    ->toggleable(),
                TextColumn::make('route.arrival')
                    ->label('Arrivée')
                    ->toggleable(),
                TextColumn::make('travelCompany.name')
                    ->label('Compagnie')
                    ->toggleable(),
                TextColumn::make('sender_name')
                    ->label('Expéditeur')
                    ->searchable(),
                TextColumn::make('recipient_name')
                    ->label('Destinataire')
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->numeric()
                    ->suffix(' F')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('scope')
                    ->label('Type')
                    ->options(\App\Data\ParcelScopeEnum::class),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(ParcelStatusEnum::class),
                SelectFilter::make('delivery_mode')
                    ->label('Mode')
                    ->options(ParcelDeliveryModeEnum::class),
            ])
            ->recordActions([
                ViewAction::make(),
                ParcelShipmentStatusActions::tableActionGroup(),
            ]);
    }
}
