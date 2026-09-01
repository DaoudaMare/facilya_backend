<?php

namespace App\Filament\Resources\TransferNetworks\Pages;

use App\Filament\Resources\TransferNetworks\TransferNetworkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransferNetworks extends ListRecords
{
    protected static string $resource = TransferNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
