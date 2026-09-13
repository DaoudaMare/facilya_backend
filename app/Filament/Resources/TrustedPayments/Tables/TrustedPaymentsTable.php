<?php

namespace App\Filament\Resources\TrustedPayments\Tables;

use App\Data\TrustedPaymentStatusEnum;
use App\Data\TrustedPayoutStatusEnum;
use App\Filament\Resources\TrustedPayments\Support\TrustedPaymentStatusActions;
use App\Models\TrustedPayment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrustedPaymentsTable
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
                    ->description(fn (TrustedPayment $record): ?string => TrustedPaymentStatusActions::nextStepHint($record)),
                TextColumn::make('payout_status')
                    ->label('Versement')
                    ->badge()
                    ->sortable(),
                TextColumn::make('buyer.name')
                    ->label('Acheteur')
                    ->searchable(),
                TextColumn::make('merchant.name')
                    ->label('Commerçant')
                    ->searchable(),
                TextColumn::make('merchandise_amount')
                    ->label('Marchandise')
                    ->numeric()
                    ->suffix(' F')
                    ->sortable(),
                TextColumn::make('payout_amount')
                    ->label('Payout')
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
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(TrustedPaymentStatusEnum::class),
                SelectFilter::make('payout_status')
                    ->label('Versement')
                    ->options(TrustedPayoutStatusEnum::class),
            ])
            ->recordActions([
                ViewAction::make(),
                TrustedPaymentStatusActions::tableActionGroup(),
            ]);
    }
}
