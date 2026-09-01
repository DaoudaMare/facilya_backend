<?php

namespace App\Filament\Resources\TravelCompanyStations\Pages;

use App\Filament\Resources\TravelCompanyStations\TravelCompanyStationResource;
use App\Services\TravelCompanyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTravelCompanyStation extends CreateRecord
{
    protected static string $resource = TravelCompanyStationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TravelCompanyService::class)->createStation($data);
    }
}
