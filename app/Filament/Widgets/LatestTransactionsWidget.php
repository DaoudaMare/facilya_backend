<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestTransactionsWidget extends TableWidget
{
    protected static ?string $heading = 'Dernières transactions';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->with('user')
                    ->latest(),
            )
            ->paginated([5])
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Client'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Paiement')
                    ->badge(),
                TextColumn::make('service_status')
                    ->label('Service')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric()
                    ->suffix(' F CFA'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->since(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
