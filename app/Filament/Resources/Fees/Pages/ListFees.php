<?php

namespace App\Filament\Resources\Fees\Pages;

use App\Filament\Pages\Configuration;
use App\Filament\Resources\Fees\FeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFees extends ListRecords
{
    protected static string $resource = FeeResource::class;

    public function mount(): void
    {
        $this->redirect(Configuration::tabUrl('frais'), navigate: false);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
