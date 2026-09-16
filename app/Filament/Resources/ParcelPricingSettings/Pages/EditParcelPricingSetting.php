<?php

namespace App\Filament\Resources\ParcelPricingSettings\Pages;

use App\Filament\Resources\ParcelPricingSettings\ParcelPricingSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditParcelPricingSetting extends EditRecord
{
    protected static string $resource = ParcelPricingSettingResource::class;

    protected static ?string $title = 'Tarifs colis';

    public function mount(int|string|null $record = null): void
    {
        $this->redirect(\App\Filament\Pages\Configuration::tabUrl('tarifs-colis'), navigate: false);
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
