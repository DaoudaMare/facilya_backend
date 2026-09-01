<?php

namespace App\Filament\Resources\TravelCompanyTrips\Pages;

use App\Filament\Resources\TravelCompanyTrips\TravelCompanyTripResource;
use App\Models\TravelCompanyTrip;
use App\Services\TravelCompanyService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTravelCompanyTrip extends EditRecord
{
    protected static string $resource = TravelCompanyTripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var TravelCompanyTrip $record */
        return app(TravelCompanyService::class)->updateTrip($record, $data);
    }
}
