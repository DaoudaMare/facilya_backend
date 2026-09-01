<?php

namespace App\Filament\Resources\TravelCompanyStations;

use App\Filament\Resources\TravelCompanyStations\Pages\CreateTravelCompanyStation;
use App\Filament\Resources\TravelCompanyStations\Pages\EditTravelCompanyStation;
use App\Filament\Resources\TravelCompanyStations\Pages\ListTravelCompanyStations;
use App\Filament\Resources\TravelCompanyStations\Schemas\TravelCompanyStationForm;
use App\Filament\Resources\TravelCompanyStations\Tables\TravelCompanyStationsTable;
use App\Models\TravelCompanyStation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TravelCompanyStationResource extends Resource
{
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
}
