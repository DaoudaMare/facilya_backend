<?php

namespace App\Filament\Resources\TrustedPayments\Pages;

use App\Filament\Resources\TrustedPayments\Support\TrustedPaymentStatusActions;
use App\Filament\Resources\TrustedPayments\TrustedPaymentResource;
use App\Models\TrustedPayment;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTrustedPayment extends ViewRecord
{
    protected static string $resource = TrustedPaymentResource::class;

    protected function getHeaderActions(): array
    {
        /** @var TrustedPayment $payment */
        $payment = $this->record;

        return array_map(
            function (Action $action) {
                return $action->after(function (): void {
                    $this->refreshFormData(['status', 'payout_status']);
                    $this->record->refresh();
                    $this->record->load('events');
                });
            },
            TrustedPaymentStatusActions::headerActionsFor($payment),
        );
    }
}
