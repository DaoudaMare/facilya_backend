<?php

namespace App\Filament\Resources\TransferNetworks;

use App\Filament\Resources\TransferNetworks\Pages\CreateTransferNetwork;
use App\Filament\Resources\TransferNetworks\Pages\EditTransferNetwork;
use App\Filament\Resources\TransferNetworks\Pages\ListTransferNetworks;
use App\Filament\Resources\TransferNetworks\Schemas\TransferNetworkForm;
use App\Filament\Resources\TransferNetworks\Tables\TransferNetworksTable;
use App\Filament\Support\AuthorizesPermissions;
use App\Models\TransferNetwork;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TransferNetworkResource extends Resource
{
    use AuthorizesPermissions;

    protected static ?string $model = TransferNetwork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'réseau';

    protected static ?string $pluralModelLabel = 'réseaux';

    protected static ?string $navigationLabel = 'Réseaux';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TransferNetworkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransferNetworksTable::configure($table);
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
            'index' => ListTransferNetworks::route('/'),
            'create' => CreateTransferNetwork::route('/create'),
            'edit' => EditTransferNetwork::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessManage('networks.manage');
    }

    public static function canCreate(): bool
    {
        return static::canAccessManage('networks.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccessManage('networks.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccessManage('networks.manage');
    }
}
