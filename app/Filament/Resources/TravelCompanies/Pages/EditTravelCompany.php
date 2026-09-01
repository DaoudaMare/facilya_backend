<?php

namespace App\Filament\Resources\TravelCompanies\Pages;

use App\Filament\Resources\TravelCompanies\TravelCompanyResource;
use App\Models\TravelCompany;
use App\Services\TravelCompanyService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTravelCompany extends EditRecord
{
    protected static string $resource = TravelCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var TravelCompany $record */
        return app(TravelCompanyService::class)->update($record, $data);
    }
}
