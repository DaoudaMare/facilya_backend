<?php

namespace App\Filament\Resources\Fees;

use App\Filament\Resources\Fees\Pages\CreateFee;
use App\Filament\Resources\Fees\Pages\EditFee;
use App\Filament\Resources\Fees\Pages\ListFees;
use App\Filament\Resources\Fees\Schemas\FeeForm;
use App\Filament\Resources\Fees\Tables\FeesTable;
use App\Models\Fee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeeResource extends Resource
{
    protected static ?string $model = Fee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'frais';

    protected static ?string $pluralModelLabel = 'frais';

    protected static ?string $navigationLabel = 'Frais';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeesTable::configure($table);
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
            'index' => ListFees::route('/'),
            'create' => CreateFee::route('/create'),
            'edit' => EditFee::route('/{record}/edit'),
        ];
    }
}
