<?php

namespace App\Filament\Resources\Fees\Tables;

use App\Data\FeePartEnum;
use App\Data\TransactionTypeEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Libellé')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('transaction_type')
                    ->label('Opération')
                    ->badge(),
                TextColumn::make('part')
                    ->label('Part')
                    ->badge(),
                TextColumn::make('mode')
                    ->label('Mode')
                    ->badge(),
                TextColumn::make('value')
                    ->label('Valeur')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('network.name')
                    ->label('Réseau')
                    ->placeholder('Tous')
                    ->toggleable(),
                TextColumn::make('counterpartNetwork.name')
                    ->label('Destination')
                    ->placeholder('Toutes')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('transaction_type')
                    ->label('Opération')
                    ->options(TransactionTypeEnum::class),
                SelectFilter::make('part')
                    ->label('Part')
                    ->options(FeePartEnum::class),
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
