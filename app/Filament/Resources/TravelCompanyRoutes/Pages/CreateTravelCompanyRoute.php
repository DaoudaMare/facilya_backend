<?php

namespace App\Filament\Resources\TravelCompanyRoutes\Pages;

use App\Filament\Resources\TravelCompanyRoutes\TravelCompanyRouteResource;
use App\Services\TravelCompanyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTravelCompanyRoute extends CreateRecord
{
    protected static string $resource = TravelCompanyRouteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TravelCompanyService::class)->createRoute($data);
    }
}
