<?php

namespace App\Filament\Resources\TransferNetworks\Pages;

use App\Filament\Resources\TransferNetworks\TransferNetworkResource;
use App\Services\TransferNetworkService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransferNetwork extends CreateRecord
{
    protected static string $resource = TransferNetworkResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TransferNetworkService::class)->create($data);
    }
}
