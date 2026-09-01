<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Data\PaymentStatusEnum;
use App\Data\ServiceStatusEnum;
use App\Data\TransactionTypeEnum;
use App\Models\Transaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('route.departure')
                    ->label('Trajet')
                    ->formatStateUsing(
                        fn ($state, Transaction $record): string => $record->route
                            ? $record->route->departure.' → '.$record->route->arrival
                            : '—',
                    )
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('trip.departure_hour')
                    ->label('Horaire')
                    ->time('H:i')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('travel_date')
                    ->label('Voyage')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('payment_status')
                    ->label('Paiement')
                    ->badge(),
                TextColumn::make('service_status')
                    ->label('Service')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric()
                    ->suffix(' F CFA')
                    ->sortable(),
                TextColumn::make('network_fee')
                    ->label('Frais réseau')
                    ->numeric()
                    ->suffix(' F CFA')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('platform_fee')
                    ->label('Frais plateforme')
                    ->numeric()
                    ->suffix(' F CFA')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(TransactionTypeEnum::class),
                SelectFilter::make('travel_company_route_id')
                    ->label('Trajet')
                    ->relationship('route', 'departure')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record): string => $record->departure.' → '.$record->arrival,
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_status')
                    ->label('Paiement')
                    ->options(PaymentStatusEnum::class),
                SelectFilter::make('service_status')
                    ->label('Service')
                    ->options(ServiceStatusEnum::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
