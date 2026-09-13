<?php

namespace App\Filament\Resources\ParcelPricingSettings;

use App\Filament\Resources\ParcelPricingSettings\Pages\EditParcelPricingSetting;
use App\Models\ParcelPricingSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\ParcelPricingSettings\Schemas\ParcelPricingSettingForm;

class ParcelPricingSettingResource extends Resource
{
    protected static ?string $model = ParcelPricingSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|\UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'tarif colis';

    protected static ?string $pluralModelLabel = 'tarifs colis';

    protected static ?string $navigationLabel = 'Tarifs colis';

    public static function form(Schema $schema): Schema
    {
        return ParcelPricingSettingForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => EditParcelPricingSetting::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
