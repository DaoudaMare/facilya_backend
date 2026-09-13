<?php

namespace App\Filament\Resources\ParcelShipments\Pages;

use App\Filament\Resources\ParcelShipments\ParcelShipmentResource;
use App\Filament\Resources\ParcelShipments\Support\ParcelShipmentStatusActions;
use App\Models\ParcelShipment;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewParcelShipment extends ViewRecord
{
    protected static string $resource = ParcelShipmentResource::class;

    protected function getHeaderActions(): array
    {
        /** @var ParcelShipment $shipment */
        $shipment = $this->record;

        return array_map(
            function (Action $action) {
                return $action->after(function (): void {
                    $this->refreshFormData(['status']);
                    $this->record->refresh();
                    $this->record->load('events');
                });
            },
            ParcelShipmentStatusActions::headerActionsFor($shipment),
        );
    }
}
