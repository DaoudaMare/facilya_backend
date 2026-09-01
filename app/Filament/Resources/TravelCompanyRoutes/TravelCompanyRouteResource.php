<?php

namespace App\Filament\Resources\TravelCompanyRoutes;

use App\Filament\Resources\TravelCompanyRoutes\Pages\CreateTravelCompanyRoute;
use App\Filament\Resources\TravelCompanyRoutes\Pages\EditTravelCompanyRoute;
use App\Filament\Resources\TravelCompanyRoutes\Pages\ListTravelCompanyRoutes;
use App\Filament\Resources\TravelCompanyRoutes\Schemas\TravelCompanyRouteForm;
use App\Filament\Resources\TravelCompanyRoutes\Tables\TravelCompanyRoutesTable;
use App\Models\TravelCompanyRoute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TravelCompanyRouteResource extends Resource
{
    protected static ?string $model = TravelCompanyRoute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'trajet';

    protected static ?string $pluralModelLabel = 'trajets';

    protected static ?string $navigationLabel = 'Trajets';

    protected static ?string $recordTitleAttribute = 'departure';

    public static function form(Schema $schema): Schema
    {
        return TravelCompanyRouteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelCompanyRoutesTable::configure($table);
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
            'index' => ListTravelCompanyRoutes::route('/'),
            'create' => CreateTravelCompanyRoute::route('/create'),
            'edit' => EditTravelCompanyRoute::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('travelCompany');
    }
}
