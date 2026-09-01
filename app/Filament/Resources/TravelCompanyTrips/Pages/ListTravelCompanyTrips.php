<?php

namespace App\Filament\Resources\TravelCompanyTrips\Pages;

use App\Filament\Resources\TravelCompanyTrips\TravelCompanyTripResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelCompanyTrips extends ListRecords
{
    protected static string $resource = TravelCompanyTripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
