<?php

namespace App\Filament\Resources\ParcelShipments;

use App\Filament\Resources\ParcelShipments\Pages\ListParcelShipments;
use App\Filament\Resources\ParcelShipments\Pages\ViewParcelShipment;
use App\Filament\Resources\ParcelShipments\Schemas\ParcelShipmentInfolist;
use App\Filament\Resources\ParcelShipments\Tables\ParcelShipmentsTable;
use App\Models\ParcelShipment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParcelShipmentResource extends Resource
{
    protected static ?string $model = ParcelShipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'colis';

    protected static ?string $pluralModelLabel = 'colis';

    protected static ?string $navigationLabel = 'Colis';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function infolist(Schema $schema): Schema
    {
        return ParcelShipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParcelShipmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParcelShipments::route('/'),
            'view' => ViewParcelShipment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'route.travelCompany', 'travelCompany', 'transaction.paymentNetwork', 'events']);
    }
}
