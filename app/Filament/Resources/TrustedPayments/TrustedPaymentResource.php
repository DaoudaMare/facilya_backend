<?php

namespace App\Filament\Resources\TrustedPayments;

use App\Filament\Resources\TrustedPayments\Pages\ListTrustedPayments;
use App\Filament\Resources\TrustedPayments\Pages\ViewTrustedPayment;
use App\Filament\Resources\TrustedPayments\Schemas\TrustedPaymentInfolist;
use App\Filament\Resources\TrustedPayments\Tables\TrustedPaymentsTable;
use App\Models\TrustedPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrustedPaymentResource extends Resource
{
    protected static ?string $model = TrustedPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'paiement confiant';

    protected static ?string $pluralModelLabel = 'paiements confiants';

    protected static ?string $navigationLabel = 'Paiements confiants';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function infolist(Schema $schema): Schema
    {
        return TrustedPaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrustedPaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrustedPayments::route('/'),
            'view' => ViewTrustedPayment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['buyer', 'merchant', 'transaction.paymentNetwork', 'paymentNetwork', 'payoutNetwork', 'events']);
    }
}
