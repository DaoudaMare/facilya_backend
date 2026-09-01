<?php

namespace App\Filament\Resources\TravelCompanyRoutes\Pages;

use App\Filament\Resources\TravelCompanyRoutes\TravelCompanyRouteResource;
use App\Models\TravelCompanyRoute;
use App\Services\TravelCompanyService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTravelCompanyRoute extends EditRecord
{
    protected static string $resource = TravelCompanyRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var TravelCompanyRoute $record */
        return app(TravelCompanyService::class)->updateRoute($record, $data);
    }
}
