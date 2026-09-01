<?php

namespace App\Filament\Resources\Fees\Pages;

use App\Filament\Resources\Fees\FeeResource;
use App\Services\FeeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFee extends CreateRecord
{
    protected static string $resource = FeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(FeeService::class)->create($data);
    }
}
