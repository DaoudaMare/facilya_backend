<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Data\PaymentStatusEnum;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markPaymentReceived')
                ->label('Paiement reçu')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->record->payment_status !== PaymentStatusEnum::RECEIVED)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Transaction $transaction */
                    $transaction = $this->record;
                    app(TransactionService::class)->markPaymentReceived($transaction);

                    Notification::make()
                        ->title('Paiement marqué comme reçu')
                        ->success()
                        ->send();
                }),
            Action::make('markServiceDelivered')
                ->label('Service livré')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->record->isPaid() && ! $this->record->isServed())
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Transaction $transaction */
                    $transaction = $this->record;
                    app(TransactionService::class)->markServiceDelivered($transaction);

                    Notification::make()
                        ->title('Service marqué comme livré')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
