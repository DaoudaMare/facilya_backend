<?php

namespace App\Filament\Resources\TravelCompanyTrips\Pages;

use App\Filament\Resources\TravelCompanyTrips\TravelCompanyTripResource;
use App\Services\TravelCompanyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTravelCompanyTrip extends CreateRecord
{
    protected static string $resource = TravelCompanyTripResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TravelCompanyService::class)->createTrip($data);
    }
}
