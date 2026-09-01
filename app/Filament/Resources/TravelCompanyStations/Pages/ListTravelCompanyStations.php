<?php

namespace App\Filament\Resources\TravelCompanyStations\Pages;

use App\Filament\Resources\TravelCompanyStations\TravelCompanyStationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelCompanyStations extends ListRecords
{
    protected static string $resource = TravelCompanyStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
