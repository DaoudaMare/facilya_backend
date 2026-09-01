<?php

namespace App\Filament\Resources\TravelCompanies\Pages;

use App\Filament\Resources\TravelCompanies\TravelCompanyResource;
use App\Services\TravelCompanyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTravelCompany extends CreateRecord
{
    protected static string $resource = TravelCompanyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TravelCompanyService::class)->create($data);
    }
}
