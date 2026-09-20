<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\Support\TransactionStatusActions;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Transaction $transaction */
        $transaction = $this->record;
        $transaction->loadMissing(['parcelShipment', 'trustedPayment']);

        $statusActions = array_map(
            function (Action $action) {
                return $action->after(function (): void {
                    $this->refreshFormData([
                        'payment_status',
                        'service_status',
                    ]);
                    $this->record->refresh();
                    $this->record->load([
                        'parcelShipment.events',
                        'parcelShipment.trustedPayment',
                        'parcelShipment.travelCompany',
                        'trustedPayment.events',
                        'trustedPayment.buyer',
                        'trustedPayment.merchant',
                        'trustedPayment.parcelShipment',
                        'relayJobs',
                    ]);
                });
            },
            TransactionStatusActions::headerActionsFor($transaction),
        );

        return [
            ...$statusActions,
            EditAction::make(),
        ];
    }
}
