<?php

namespace App\Filament\Resources\TransferNetworks\Pages;

use App\Filament\Resources\TransferNetworks\TransferNetworkResource;
use App\Models\TransferNetwork;
use App\Services\TransferNetworkService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTransferNetwork extends EditRecord
{
    protected static string $resource = TransferNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var TransferNetwork $record */
        return app(TransferNetworkService::class)->update($record, $data);
    }
}
