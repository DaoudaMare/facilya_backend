<?php

namespace App\Filament\Resources\TravelCompanyRoutes\Pages;

use App\Filament\Resources\TravelCompanyRoutes\TravelCompanyRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelCompanyRoutes extends ListRecords
{
    protected static string $resource = TravelCompanyRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
