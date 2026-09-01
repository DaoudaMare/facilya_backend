<?php

namespace App\Filament\Resources\TransferNetworks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransferNetworksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('can_send')
                    ->label('Envoi')
                    ->boolean(),
                IconColumn::make('can_receive')
                    ->label('Réception')
                    ->boolean(),
                TextColumn::make('receive_phone')
                    ->label('N° réception')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('payment_ussd')
                    ->label('USSD paiement')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
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
