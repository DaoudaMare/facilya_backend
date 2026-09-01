<?php

namespace App\Filament\Resources\TravelCompanies\Pages;

use App\Filament\Resources\TravelCompanies\TravelCompanyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTravelCompany extends ViewRecord
{
    protected static string $resource = TravelCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
