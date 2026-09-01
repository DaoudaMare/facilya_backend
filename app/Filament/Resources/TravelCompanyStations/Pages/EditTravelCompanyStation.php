<?php

namespace App\Filament\Resources\TravelCompanyStations\Pages;

use App\Filament\Resources\TravelCompanyStations\TravelCompanyStationResource;
use App\Models\TravelCompanyStation;
use App\Services\TravelCompanyService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTravelCompanyStation extends EditRecord
{
    protected static string $resource = TravelCompanyStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var TravelCompanyStation $record */
        return app(TravelCompanyService::class)->updateStation($record, $data);
    }
}
