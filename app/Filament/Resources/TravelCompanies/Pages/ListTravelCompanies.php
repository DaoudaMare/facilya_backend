<?php

namespace App\Filament\Resources\TravelCompanies\Pages;

use App\Filament\Resources\TravelCompanies\TravelCompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelCompanies extends ListRecords
{
    protected static string $resource = TravelCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
