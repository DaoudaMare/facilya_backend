<?php

namespace App\Filament\Resources\ParcelShipments;

use App\Filament\Resources\ParcelShipments\Pages\ListParcelShipments;
use App\Filament\Resources\ParcelShipments\Pages\ViewParcelShipment;
use App\Filament\Resources\ParcelShipments\Schemas\ParcelShipmentInfolist;
use App\Filament\Resources\ParcelShipments\Tables\ParcelShipmentsTable;
use App\Filament\Support\AuthorizesPermissions;
use App\Models\ParcelShipment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ParcelShipmentResource extends Resource
{
    use AuthorizesPermissions;

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
            ->with(['user', 'route.travelCompany', 'travelCompany', 'transaction.paymentNetwork', 'trustedPayment', 'events']);
    }

    public static function canViewAny(): bool
    {
        return static::canAccessView('parcels.view', 'parcels.manage');
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessView('parcels.view', 'parcels.manage');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccessManage('parcels.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccessManage('parcels.manage');
    }
}
