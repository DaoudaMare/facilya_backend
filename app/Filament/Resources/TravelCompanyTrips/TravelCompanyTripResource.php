<?php

namespace App\Filament\Resources\TravelCompanyTrips;

use App\Filament\Resources\TravelCompanyTrips\Pages\CreateTravelCompanyTrip;
use App\Filament\Resources\TravelCompanyTrips\Pages\EditTravelCompanyTrip;
use App\Filament\Resources\TravelCompanyTrips\Pages\ListTravelCompanyTrips;
use App\Filament\Resources\TravelCompanyTrips\Schemas\TravelCompanyTripForm;
use App\Filament\Resources\TravelCompanyTrips\Tables\TravelCompanyTripsTable;
use App\Models\TravelCompanyTrip;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TravelCompanyTripResource extends Resource
{
    protected static ?string $model = TravelCompanyTrip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'horaire';

    protected static ?string $pluralModelLabel = 'horaires';

    protected static ?string $navigationLabel = 'Horaires';

    public static function form(Schema $schema): Schema
    {
        return TravelCompanyTripForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelCompanyTripsTable::configure($table);
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
            'index' => ListTravelCompanyTrips::route('/'),
            'create' => CreateTravelCompanyTrip::route('/create'),
            'edit' => EditTravelCompanyTrip::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['route.travelCompany', 'station']);
    }
}
