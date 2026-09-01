<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Services\PromotionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PromotionService::class)->create($data);
    }
}
