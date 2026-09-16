<?php

namespace App\Filament\Resources\TravelCompanyStations;

use App\Filament\Resources\TravelCompanyStations\Pages\CreateTravelCompanyStation;
use App\Filament\Resources\TravelCompanyStations\Pages\EditTravelCompanyStation;
use App\Filament\Resources\TravelCompanyStations\Pages\ListTravelCompanyStations;
use App\Filament\Resources\TravelCompanyStations\Schemas\TravelCompanyStationForm;
use App\Filament\Resources\TravelCompanyStations\Tables\TravelCompanyStationsTable;
use App\Filament\Support\AuthorizesPermissions;
use App\Models\TravelCompanyStation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TravelCompanyStationResource extends Resource
{
    use AuthorizesPermissions;

    protected static ?string $model = TravelCompanyStation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'gare';

    protected static ?string $pluralModelLabel = 'gares';

    protected static ?string $navigationLabel = 'Gares';

    protected static ?string $recordTitleAttribute = 'station_name';

    public static function form(Schema $schema): Schema
    {
        return TravelCompanyStationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelCompanyStationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTravelCompanyStations::route('/'),
            'create' => CreateTravelCompanyStation::route('/create'),
            'edit' => EditTravelCompanyStation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('travelCompany');
    }

    public static function canViewAny(): bool
    {
        return static::canAccessManage('travel.manage');
    }

    public static function canCreate(): bool
    {
        return static::canAccessManage('travel.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccessManage('travel.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccessManage('travel.manage');
    }
}
