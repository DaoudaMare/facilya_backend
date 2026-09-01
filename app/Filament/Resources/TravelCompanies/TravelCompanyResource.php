<?php

namespace App\Filament\Resources\TravelCompanies;

use App\Filament\Resources\TravelCompanies\Pages\CreateTravelCompany;
use App\Filament\Resources\TravelCompanies\Pages\EditTravelCompany;
use App\Filament\Resources\TravelCompanies\Pages\ListTravelCompanies;
use App\Filament\Resources\TravelCompanies\Pages\ViewTravelCompany;
use App\Filament\Resources\TravelCompanies\Schemas\TravelCompanyForm;
use App\Filament\Resources\TravelCompanies\Schemas\TravelCompanyInfolist;
use App\Filament\Resources\TravelCompanies\Tables\TravelCompaniesTable;
use App\Models\TravelCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TravelCompanyResource extends Resource
{
    protected static ?string $model = TravelCompany::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Transport';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'compagnie';

    protected static ?string $pluralModelLabel = 'compagnies';

    protected static ?string $navigationLabel = 'Compagnies';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TravelCompanyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TravelCompanyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelCompaniesTable::configure($table);
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
            'index' => ListTravelCompanies::route('/'),
            'create' => CreateTravelCompany::route('/create'),
            'view' => ViewTravelCompany::route('/{record}'),
            'edit' => EditTravelCompany::route('/{record}/edit'),
        ];
    }
}
